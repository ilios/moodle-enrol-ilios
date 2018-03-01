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
     * Destructor
     */
    public function __destruct() {
        $accesstoken = $this->iliosclient->getAccessToken();
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
     * @param progress_trace
     * @return int exit code, 0 means ok, 2 means plugin disabled.
     * @throws \Exception
     */
    public function sync($trace) {
        global $CFG;
        if (!enrol_is_enabled('ilios')) {
            return 2;
        }

        require_once("$CFG->dirroot/enrol/ilios/locallib.php");

        enrol_ilios_sync($trace);

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
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/ilios/locallib.php");
        $trace = new null_progress_trace();
        enrol_ilios_sync($trace, $instance->courseid);
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
     */
    public function get_manual_enrol_button(course_enrolment_manager $manager) {
        $course = $manager->get_course();
        if (!$this->can_add_new_instances($course->id)) {
            return false;
        }

        $iliosurl = new moodle_url('/enrol/ilios/edit.php', array('courseid' => $course->id));
        $button = new enrol_user_button($iliosurl, get_string('enrol', 'enrol_ilios'), 'get');

        return $button;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

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

            require_once("$CFG->dirroot/enrol/ilios/locallib.php");
            $trace = new null_progress_trace();
            enrol_ilios_sync($trace, $course->id);
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

            require_once("$CFG->dirroot/enrol/ilios/locallib.php");
            $trace = new null_progress_trace();
            enrol_ilios_sync($trace, $course->id);
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
     * @param  string $groupType singular noun of the group type, e.g. cohort, learnerGroup
     * @param  string $groupId   the id for the corresponding group type, e.g. cohort id,  learner group id.
     *
     * @return object groupObject returned by the ILIOS api in addition of populating
     *                            the instructor array with correct ids, which is to
     *                            iterate into offerings and ilmSessions and fetch the
     *                            associated instructors and instructor groups. Should
     *                            also iterate into subgroups.
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
