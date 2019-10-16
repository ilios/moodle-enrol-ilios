<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Ilios enrolment plugin.
 *
 * @package    enrol_ilios
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/filelib.php';

/**
 * Ilios enrolment plugin implementation.
 * @author    Carson Tam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_ilios_plugin extends enrol_plugin {

    /** @var \local_iliosapiclient\ilios_client $iliosclient */
    protected $iliosclient;

    /**
     * Constructor
     */
    public function __construct() {
        $accesstoken = new stdClass;
        $accesstoken->token = $this->get_config('apikey');
        $accesstoken->expires = $this->get_config('apikeyexpires');

        $this->iliosclient = new \local_iliosapiclient\ilios_client($this->get_config('host_url'),
                                              $this->get_config('userid'),
                                              $this->get_config('secret'),
                                              $accesstoken);
    }

    /**
     * Updates the Ilios API token in the plugin configuration.
     *
     * @param \local_iliosapiclient\ilios_client $ilios_client
     */
    protected function save_api_token(\local_iliosapiclient\ilios_client $ilios_client) {
        $accesstoken = $ilios_client->getAccessToken();
        $apikey = $this->get_config('apikey');
        if (!empty($accesstoken) && ($apikey !== $accesstoken->token)) {
            $this->set_config('apikey', $accesstoken->token);
            $this->set_config('apikeyexpires', $accesstoken->expires);
        }
    }

    /**
     * @inheritdoc
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ilios:config', $context);
    }

    /**
     * @inheritdoc
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ilios:config', $context);
    }

    /**
     * Returns the Ilios Client for API access
     *
     * @return \local_iliosapiclient\ilios_client
     */
    public function get_http_client() {
        return $this->iliosclient;
    }


    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     * @throws \Exception
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginshortname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();

            $syncfield = $instance->customchar1;
            $syncid = $instance->customint1;

            // $groups = $this->iliosclient->getbyids($syncfield.'s', $syncid);

            // if (!empty($groups)) {
            //     $group = $groups[0];
            //     $groupname = format_string($group->title, true, array('context'=>context::instance_by_id($instance->courseid)));
            // } else
            {
                $groupname = get_string('pluginshortname', 'enrol_'.$enrol);
                $syncinfo = json_decode($instance->customtext1);
                if (!empty($syncinfo)) {
                    $schooltitle = $syncinfo->school->title;
                    $programtitle = $syncinfo->program->shorttitle;
                    $cohorttitle = $syncinfo->cohort->title;
                    $groupname .= ": ". $schooltitle ."/".$programtitle."/".$cohorttitle;
                    if (isset($syncinfo->learnerGroup)) {
                        $grouptitle = $syncinfo->learnerGroup->title;
                        $groupname .= '/'.$grouptitle;
                    }
                    if (isset($syncinfo->subGroup)) {
                        $grouptitle = $syncinfo->subGroup->title;
                        $groupname .= '/'.$grouptitle;
                    }
                }
            }

            if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $role = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
                if (empty($instance->customint2)) {
                    $groupname .= ' (Learner => '.$role;
                } else {
                    $groupname .= ' (Instructor => '.$role;
                }

                $groupid = $instance->customint6;
                $group = groups_get_group( $groupid, 'name' );
                if (!empty($group) && isset($group->name)) {
                    $groupname .= ', ' . $group->name;
                }
                $groupname .= ')';
            } else {
                $groupid = $instance->customint6;
                $group = groups_get_group( $groupid, 'name' );
                if (!empty($group) && isset($group->name)) {
                    $groupname .= ' (' . $group->name. ')';
                }
            }

            return $groupname;

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     * @throws \Exception
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return NULL;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/ilios/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid
     * @return bool
     * @throws \Exception
     */
    protected function can_add_new_instances($courseid) {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/ilios:config', $coursecontext)) {
            return false;
        }
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     * @throws \Exception
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ilios') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/ilios:config', $context)) {
            $editlink = new moodle_url("/enrol/ilios/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }


    /**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @param int|NULL $courseid one course, empty mean all
     * @return int exit code, 0 means ok, 2 means plugin disabled.
     * @throws \Exception
     */
    public function sync($trace, $courseid = NULL) {
        global $CFG, $DB;

        require_once("$CFG->dirroot/group/lib.php");

        if (!enrol_is_enabled('ilios')) {
            // Purge all roles if ilios sync disabled, those can be recreated later here by cron or CLI.
            $trace->output('Ilios enrolment sync plugin is disabled, unassigning all plugin roles and stopping.');
            role_unassign_all(array('component'=>'enrol_ilios'));
            return 2;
        }

        // Unfortunately this may take a long time, this script can be interrupted without problems.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Starting user enrolment synchronisation...');

        $allroles = get_all_roles();
        $iliosusers = array(); // cache user data

        $http  = $this->get_http_client();

        $unenrolaction = $this->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        // Iterate through all not enrolled yet users.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT *
              FROM {enrol} e
             WHERE e.enrol = 'ilios' $onecourse";

        $params = array();
        $params['courseid'] = $courseid;
        $params['suspended'] = ENROL_USER_SUSPENDED;
        $instances = $DB->get_recordset_sql($sql, $params);

        foreach ($instances as $instance) {
            $synctype = $instance->customchar1;
            $syncid = $instance->customint1;

            if (!empty($instance->customint2)) {
                // Need to get instructor ids.  This function takes longer to run.
                $group = $this->getGroupData($synctype, $syncid);
            } else {
                // No need to get instructor ids.
                $group = $http->getbyid( $synctype.'s', $syncid);
            }

            if (empty($group)) {
                $trace->output("skipping: Unable to fetch data for Ilios $synctype ID $syncid.", 1);
                continue;
            }

            if (!empty($group)) {

                $enrolleduserids = array();    // keep a list of enrolled user's Moodle userid (both learners and instructors).
                $users = []; // ilios user in that group

                if (!empty($instance->customint2)) {
                    $trace->output("Enrolling instructors to Course ID ".$instance->courseid." with Role ID ".$instance->roleid." through Ilios Sync ID ".$instance->id.".");
                    $users = $http->getbyids('users', $group->instructors);
                } else {
                    $trace->output("Enrolling students to Course ID ".$instance->courseid." with Role ID ".$instance->roleid." through Ilios Sync ID ".$instance->id.".");
                    $users = $http->getbyids('users', $group->users);
                }
                $trace->output(count($users) . " Ilios users found.");

                foreach ($users as $user) {
                    // Fetch user info if not cached in $iliosusers
                    if (!isset($iliosusers[$user->id])) {
                        $iliosusers[$user->id] = null;
                        if (!empty($user->campusId)) {
                            $urec = $DB->get_record('user', array("idnumber" => $user->campusId));
                            if (!empty($urec)) {
                                $iliosusers[$user->id] = array( 'id' => $urec->id,
                                                                'syncfield' => $urec->idnumber );
                            }
                        }
                    }

                    if ($iliosusers[$user->id] === null) {
                        if (!empty($user->campusId)) {
                            $trace->output("skipping: Cannot find campusId ".$user->campusId." that matches Moodle user field 'idnumber'.", 1);
                        } else {
                            $trace->output("skipping: Ilios user ".$user->id." does not have a 'campusId' field.", 1);
                        }
                    } else {
                        $enrolleduserids[] = $userid = $iliosusers[$user->id]['id'];

                        $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));

                        // Continue if already enrolled with active status
                        if (!empty($ue) && ENROL_USER_ACTIVE === (int) $ue->status) {
                            continue;
                        }

                        // Enroll user
                        $this->enrol_user($instance, $userid, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
                        if (!empty($ue) && ENROL_USER_ACTIVE !== (int) $ue->status) {
                            $trace->output("changing enrollment status to '" . ENROL_USER_ACTIVE . "' from '{$ue->status}': userid $userid ==> courseid ".$instance->courseid, 1);
                        } else {
                            $trace->output("enrolling with " . ENROL_USER_ACTIVE . " status: userid $userid ==> courseid ".$instance->courseid, 1);
                        }
                    }
                }

                // Unenrol as necessary.
                $trace->output("Unenrolling users from Course ID ".$instance->courseid." with Role ID ".$instance->roleid." that no longer associate with Ilios Sync ID ".$instance->id.".");

                $sql = "SELECT ue.*
                      FROM {user_enrolments} ue
                      WHERE ue.enrolid = $instance->id";

                if (!empty($enrolleduserids)) {
                    $sql .= " AND ue.userid NOT IN ( ".implode(",", $enrolleduserids)." )";
                }

                $rs = $DB->get_recordset_sql($sql);
                foreach($rs as $ue) {
                    if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                        // Remove enrolment together with group membership, grades, preferences, etc.
                        $this->unenrol_user($instance, $ue->userid);
                        $trace->output("unenrolling: $ue->userid ==> ".$instance->courseid." via Ilios $synctype $syncid", 1);
                    } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
                        // Just disable and ignore any changes.
                        if ($ue->status != ENROL_USER_SUSPENDED) {
                            $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                            $context = context_course::instance($instance->courseid);
                            role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_ilios', 'itemid'=>$instance->id));
                            $trace->output("suspending and unsassigning all roles: userid ".$ue->userid." ==> courseid ".$instance->courseid, 1);
                        }
                    }
                }
                $rs->close();
            }
        }
        $instances->close();
        unset($iliosusers);

        // Now assign all necessary roles to enrolled users - skip suspended instances and users.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT e.roleid, ue.userid, c.id AS contextid, e.id AS itemid, e.courseid
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'ilios' AND e.status = :statusenabled $onecourse)
              JOIN {role} r ON (r.id = e.roleid)
              JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :coursecontext)
              JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
         LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.userid = ue.userid AND ra.itemid = e.id AND ra.component = 'enrol_ilios' AND e.roleid = ra.roleid)
             WHERE ue.status = :useractive AND ra.id IS NULL";
        $params = array();
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $ra) {
            role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_ilios', $ra->itemid);
            $trace->output("assigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
        }
        $rs->close();

        // Remove unwanted roles - sync role can not be changed, we only remove role when unenrolled.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
              FROM {role_assignments} ra
              JOIN {context} c ON (c.id = ra.contextid AND c.contextlevel = :coursecontext)
              JOIN {enrol} e ON (e.id = ra.itemid AND e.enrol = 'ilios' $onecourse)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = :useractive)
             WHERE ra.component = 'enrol_ilios' AND (ue.id IS NULL OR e.status <> :statusenabled)";
        $params = array();
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $ra) {
            role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_ilios', $ra->itemid);
            $trace->output("unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
        }
        $rs->close();

        // Finally sync groups.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

        // Remove invalid.
        $sql = "SELECT gm.*, e.courseid, g.name AS groupname
              FROM {groups_members} gm
              JOIN {groups} g ON (g.id = gm.groupid)
              JOIN {enrol} e ON (e.enrol = 'ilios' AND e.courseid = g.courseid $onecourse)
              JOIN {user_enrolments} ue ON (ue.userid = gm.userid AND ue.enrolid = e.id)
             WHERE gm.component='enrol_ilios' AND gm.itemid = e.id AND g.id <> e.customint6";
        $params = array();
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $gm) {
            groups_remove_member($gm->groupid, $gm->userid);
            $trace->output("removing user from group: $gm->userid ==> $gm->courseid - $gm->groupname", 1);
        }
        $rs->close();

        // Add missing.
        $sql = "SELECT ue.*, g.id AS groupid, e.courseid, g.name AS groupname
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'ilios' $onecourse)
              JOIN {groups} g ON (g.courseid = e.courseid AND g.id = e.customint6)
              JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
         LEFT JOIN {groups_members} gm ON (gm.groupid = g.id AND gm.userid = ue.userid)
             WHERE gm.id IS NULL";
        $params = array();
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $ue) {
            groups_add_member($ue->groupid, $ue->userid, 'enrol_ilios', $ue->enrolid);
            $trace->output("adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname", 1);
        }
        $rs->close();

        $trace->output('...user enrolment synchronisation finished.');

        // cleanup
        $this->save_api_token($http);

        return 0;
    }

    // /**
    //  * Called after updating/inserting course.
    //  *
    //  * @param bool $inserted true if course just inserted
    //  * @param stdClass $course
    //  * @param stdClass $data form data
    //  * @return void
    //  */
    // public function course_updated($inserted, $course, $data) {
    //     // It turns out there is no need for cohorts to deal with this hook, see MDL-34870.
    // }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     * @throws \Exception
     */
    public function update_status($instance, $newstatus) {
        parent::update_status($instance, $newstatus);

        $trace = new null_progress_trace();
        $this->sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     * @throws \Exception
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/ilios:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Returns a button to enrol a ilios or its users through the manual enrolment plugin.
     *
     * @param course_enrolment_manager $manager
     * @return enrol_user_button
     * @throws \Exception
     */
    // public function get_manual_enrol_button(course_enrolment_manager $manager) {
    //     $course = $manager->get_course();
    //     if (!$this->can_add_new_instances($course->id)) {
    //         return false;
    //     }

    //     $iliosurl = new moodle_url('/enrol/ilios/edit.php', array('courseid' => $course->id));
    //     $button = new enrol_user_button($iliosurl, get_string('enrol', 'enrol_ilios'), 'get');

    //     return $button;
    // }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     * @throws \Exception
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;

        if (!$step->get_task()->is_samesite()) {
            // No ilios restore from other sites.
            $step->set_mapping('enrol', $oldid, 0);
            return;
        }

        if (!empty($data->customint6)) {
            $data->customint6 = $step->get_mappingid('group', $data->customint6);
        }

        // if ($data->roleid and $DB->record_exists('cohort', array('id'=>$data->customint1))) {
        if ($data->roleid) {
            $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1, 'customchar1'=>$data->customchar1, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            $trace = new null_progress_trace();
            $this->sync($trace, $course->id);
            $trace->finished();

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1, 'customerchar1'=>$data->customchar1, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $data->status = ENROL_INSTANCE_DISABLED;
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            $trace = new null_progress_trace();
            $this->sync($trace, $course->id);
            $trace->finished();

        } else {
            $step->set_mapping('enrol', $oldid, 0);
        }
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     * @throws \Exception
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        // ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored
        // but without roles and suspended.

        if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        // Nothing to do here, the group members are added in $this->restore_group_restored()
        return;
    }

    /**
     * Recursive get for learner group data with instructors info, to compensate
     * something that the ILIOS API fails to do!
     *
     * @param  string $grouptype singular noun of the group type, e.g. cohort, learnerGroup
     * @param  string $groupid   the id for the corresponding group type, e.g. cohort id,  learner group id.
     *
     * @return mixed returned by the ILIOS api in addition of populating
     *               the instructor array with correct ids, which is to
     *               iterate into offerings and ilmSessions and fetch the
     *               associated instructors and instructor groups. Should
     *               also iterate into subgroups.
     * @throws \Exception
     */
    public function getGroupData($grouptype, $groupid) {
        $client = $this->get_http_client();
        // Ilios API uses a plural noun, append an 's'.
        $group = $client->getbyid( $grouptype.'s', $groupid );

        if ($grouptype === 'learnerGroup') {
            $group->instructors = $this->getInstructorIdsFromGroup($grouptype, $groupid);
            asort($group->instructors);
        }

        return $group;
    }

    /**
     * @param $grouptype
     * @param $groupid
     *
     * @return array
     * @throws moodle_exception
     */
    private function getInstructorIdsFromGroup( $grouptype, $groupid ) {

        $client = $this->get_http_client();

        // Ilios API uses a plural noun, append an 's'.
        $group = $client->getbyid( $grouptype.'s', $groupid );

        $instructorGroupIds = array();
        $instructorIds = array();

        // get instructors/instructor-groups from the offerings that this learner group is being taught in.
        if (!empty($group->offerings)) {
            $offerings = $client->getbyids('offerings', $group->offerings);

            foreach ($offerings as $offering) {
                if (empty($offering->instructors)) {
                    // no instructor AND no instructor groups have been set for this offering.
                    // fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorIds = array_merge($instructorIds, $group->instructors);
                    $instructorGroupIds = array_merge($instructorGroupIds, $group->instructorGroups);
                } else {
                    // if there are instructors and/or instructor-groups set on the offering,
                    // then use these.
                    $instructorIds = array_merge($instructorIds, $offering->instructors);
                    $instructorGroupIds = array_merge($instructorGroupIds, $offering->instructorGroups);
                }
            }

        }

        // get instructors/instructor-groups from the ilm sessions that this learner group is being taught in.
        // (this is a rinse/repeat from offerings-related code above)
        if (!empty($group->ilmSessions)) {
            $ilms = $client->getbyids('ilmSessions', $group->ilmSessions);

            foreach ($ilms as $ilm) {
                if (empty($ilm->instructors) && empty($ilm->instructorGroups)) {
                    // no instructor AND no instructor groups have been set for this offering.
                    // fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorIds = array_merge($instructorIds, $group->instructors);
                    $instructorGroupIds = array_merge($instructorGroupIds, $group->instructorGroups);
                } else {
                    // if there are instructors and/or instructor-groups set on the offering,
                    // then use these.
                    $instructorIds = array_merge($instructorIds, $ilm->instructors);
                    $instructorGroupIds = array_merge($instructorGroupIds, $ilm->instructorGroups);
                }
            }
        }

        // get instructors from sub-learnerGroups
        if (!empty($group->children)) {
            foreach($group->children as $subgroupid) {
                $instructorIds = array_merge($instructorIds, $this->getInstructorIdsFromGroup('learnerGroup', $subgroupid));
                // We don't care about instructor groups here, we will merge instructor groups into the $instructorIds array later.
            }
        }

        // next, get the ids of all instructors from the instructor-groups that we determined as relevant earlier.
        // but first.. let's de-dupe them.
        $instructorGroupIds = array_unique($instructorGroupIds);
        if (!empty($instructorGroupIds)) {
            $instructorGroups = $client->getbyids('instructorGroups', $instructorGroupIds);
            foreach ($instructorGroups as $instructorGroup) {
                $instructorIds = array_merge($instructorIds, $instructorGroup->users);
            }
        }

        // finally, we retrieve all the users that were identified as relevant instructors earlier.
        $instructorIds = array_unique($instructorIds);

        return $instructorIds;
    }
}

/**
 * Prevent removal of enrol roles.
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_ilios_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}
