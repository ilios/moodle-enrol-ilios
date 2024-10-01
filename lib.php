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

use local_iliosapiclient\ilios_client;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

/**
 * Ilios enrolment plugin implementation.
 * @author    Carson Tam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_ilios_plugin extends enrol_plugin {
    /**
     * @var ilios_client The Ilios API client.
     */
    protected ilios_client $apiclient;

    /**
     * @var string the plugin settings key for the API access token.
     */
    public const SETTINGS_API_ACCESS_TOKEN = 'apikey';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->apiclient = new ilios_client($this->get_config('host_url', ''), new curl());
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_delete_instance($instance): bool {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ilios:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_hide_show_instance($instance): bool {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ilios:config', $context);
    }

    /**
     * Returns the Ilios Client for API access.
     *
     * @return ilios_client The Ilios API client.
     */
    public function get_api_client(): ilios_client {
        return $this->apiclient;
    }

    /**
     * Retrieves the Ilios API access token from the plugin configuration.
     *
     * @return string The API access token.
     */
    public function get_api_access_token(): string {
        return $this->get_config(self::SETTINGS_API_ACCESS_TOKEN, '');
    }

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance An enrol instance (or NULL for *this* instance).
     * @return string The name of this enrol instance.
     * @throws Exception
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginshortname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
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

            if ($role = $DB->get_record('role', ['id' => $instance->roleid])) {
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
            return format_string($instance->name, true, ['context' => context_course::instance($instance->courseid)]);
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid The course ID.
     * @return moodle_url|null The page URL to the new instance form, or NULL if not allowed.
     * @throws Exception
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return null;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/ilios/edit.php', ['courseid' => $courseid]);
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid The course ID.
     * @return bool TRUE if the current user can add new enrolment instances in the given course, FALSE otherwise.
     * @throws Exception
     */
    protected function can_add_new_instances($courseid): bool {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) || !has_capability('enrol/ilios:config', $coursecontext)) {
            return false;
        }
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance The enrol instance.
     * @return array The list of edit icons und URLs.
     * @throws Exception
     */
    public function get_action_icons(stdClass $instance): array {
        global $OUTPUT;

        if ($instance->enrol !== 'ilios') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = [];

        if (has_capability('enrol/ilios:config', $context)) {
            $editlink = new moodle_url("/enrol/ilios/edit.php", ['courseid' => $instance->courseid, 'id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    ['class' => 'iconsmall']));
        }

        return $icons;
    }

    /**
     * Execute all sync jobs, or all sync jobs for a given course.
     *
     * @param progress_trace $trace The progress tracer for this task run.
     * @param int|NULL $courseid The course ID, or NULL if all sync jobs should be executed.
     * @return int exit code, 0 means ok, 2 means plugin disabled.
     * @throws Exception
     */
    public function sync($trace, $courseid = null): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        $apiclient = $this->get_api_client();
        $accesstoken = $this->get_api_access_token();

        if (!enrol_is_enabled('ilios')) {
            // Purge all roles if ilios sync disabled, those can be recreated later here by cron or CLI.
            $trace->output('Ilios enrolment sync plugin is disabled, unassigning all plugin roles and stopping.');
            role_unassign_all(['component' => 'enrol_ilios']);
            return 2;
        }

        // Unfortunately this may take a long time, this script can be interrupted without problems.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Starting user enrolment synchronisation...');

        $allroles = get_all_roles();
        $iliosusers = []; // Cache user data.

        $unenrolaction = $this->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        // Iterate through all not enrolled yet users.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT *
              FROM {enrol} e
             WHERE e.enrol = 'ilios' $onecourse";

        $params = [];
        $params['courseid'] = $courseid;
        $params['suspended'] = ENROL_USER_SUSPENDED;
        $instances = $DB->get_recordset_sql($sql, $params);

        foreach ($instances as $instance) {
            $synctype = $instance->customchar1;
            $syncid = $instance->customint1;

            if (!empty($instance->customint2)) {
                // Need to get instructor ids.  This function takes longer to run.
                $group = $this->get_group_data($synctype, $syncid);
            } else {
                // No need to get instructor ids.
                $group = $apiclient->get_by_id($accesstoken, $synctype.'s', $syncid);
            }

            if (empty($group)) {
                $trace->output("skipping: Unable to fetch data for Ilios $synctype ID $syncid.", 1);
                continue;
            }

            $enrolleduserids = []; // Keep a list of enrolled user's Moodle userid (both learners and instructors).
            $users = []; // Ilios users in that group.
            $suspendenrolments = []; // List of user enrollments to suspend.

            $users = [];

            if (!empty($instance->customint2)) {
                if (!empty($group->instructors)) {
                    $trace->output(
                        "Enrolling instructors to Course ID "
                        . $instance->courseid
                        . " with Role ID "
                        . $instance->roleid
                        . " through Ilios Sync ID "
                        . $instance->id
                        . "."
                    );
                    $users = $apiclient->get_by_ids($accesstoken, 'users', $group->instructors);
                }
            } else if (!empty($group->users)) {
                $trace->output(
                    "Enrolling students to Course ID "
                    . $instance->courseid
                    . " with Role ID "
                    . $instance->roleid
                    . " through Ilios Sync ID " .
                    $instance->id
                    . "."
                );
                $users = $apiclient->get_by_ids($accesstoken, 'users', $group->users);
            }
            $trace->output(count($users) . " Ilios users found.");

            foreach ($users as $user) {
                // Fetch user info if not cached in $iliosusers.
                if (!isset($iliosusers[$user->id])) {
                    $iliosusers[$user->id] = null;
                    if (!empty($user->campusId)) {
                        $urec = $DB->get_record('user', ["idnumber" => $user->campusId]);
                        if (!empty($urec)) {
                            $iliosusers[$user->id] = ['id' => $urec->id, 'syncfield' => $urec->idnumber];
                        }
                    }
                }

                if ($iliosusers[$user->id] === null) {
                    if (!empty($user->campusId)) {
                        $trace->output(
                            "skipping: Cannot find campusId "
                            . $user->campusId
                            . " that matches Moodle user field 'idnumber'."
                            , 1);
                    } else {
                        $trace->output(
                            "skipping: Ilios user "
                            . $user->id
                            . " does not have a 'campusId' field."
                            , 1
                        );
                    }
                } else {
                    $enrolleduserids[] = $userid = $iliosusers[$user->id]['id'];

                    $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);

                    // Don't enroll disabled Ilios users that are currently not enrolled.
                    if (empty($ue) && !$user->enabled) {
                        continue;
                    }

                    // Don't re-enroll suspended enrollments for disabled Ilios users.
                    if (!empty($ue) && ENROL_USER_SUSPENDED === (int)$ue->status && !$user->enabled) {
                        continue;
                    }

                    // Flag actively enrolled users that are disabled in Ilios
                    // for enrollment suspension further downstream.
                    if (!empty($ue) && ENROL_USER_ACTIVE === (int)$ue->status && !$user->enabled) {
                        $suspendenrolments[] = $ue;
                        continue;
                    }

                    // Continue if already enrolled with active status.
                    if (!empty($ue) && ENROL_USER_ACTIVE === (int)$ue->status) {
                        continue;
                    }

                    // Enroll user.
                    $this->enrol_user(
                        $instance,
                        $userid,
                        $instance->roleid,
                        0,
                        0,
                        ENROL_USER_ACTIVE
                    );
                    if (!empty($ue) && ENROL_USER_ACTIVE !== (int)$ue->status) {
                        $trace->output(
                            "changing enrollment status to '"
                            . ENROL_USER_ACTIVE
                            . "' from '{$ue->status}': userid $userid ==> courseid "
                            . $instance->courseid
                            , 1
                        );
                    } else {
                        $trace->output(
                            "enrolling with "
                            . ENROL_USER_ACTIVE
                            . " status: userid $userid ==> courseid "
                            . $instance->courseid
                            , 1
                        );
                    }
                }
            }

            // Suspend active enrollments for users that are disabled in Ilios.
            foreach ($suspendenrolments as $ue) {
                $trace->output(
                    "Suspending enrollment for disabled Ilios user: userid "
                    . " {$ue->userid} ==> courseid {$instance->courseid}."
                    , 1
                );
                $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
            }

            // Unenrol as necessary.
            $trace->output(
                "Unenrolling users from Course ID "
                . $instance->courseid." with Role ID "
                . $instance->roleid
                . " that no longer associate with Ilios Sync ID "
                . $instance->id
                . "."
            );

            $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  WHERE ue.enrolid = $instance->id";

            if (!empty($enrolleduserids)) {
                $sql .= " AND ue.userid NOT IN ( ".implode(",", $enrolleduserids)." )";
            }

            $rs = $DB->get_recordset_sql($sql);
            foreach ($rs as $ue) {
                if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                    // Remove enrolment together with group membership, grades, preferences, etc.
                    $this->unenrol_user($instance, $ue->userid);
                    $trace->output(
                        "unenrolling: $ue->userid ==> "
                        . $instance->courseid
                        . " via Ilios $synctype $syncid"
                        , 1
                    );
                } else { // Would be ENROL_EXT_REMOVED_SUSPENDNOROLES.
                    // Just disable and ignore any changes.
                    if ($ue->status != ENROL_USER_SUSPENDED) {
                        $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                        $context = context_course::instance($instance->courseid);
                        role_unassign_all([
                            'userid' => $ue->userid,
                            'contextid' => $context->id,
                            'component' => 'enrol_ilios',
                            'itemid' => $instance->id,
                        ]);
                        $trace->output(
                            "suspending and unsassigning all roles: userid "
                            . $ue->userid
                            . " ==> courseid "
                            . $instance->courseid
                            , 1
                        );
                    }
                }
            }
            $rs->close();
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
         LEFT JOIN {role_assignments} ra ON (
              ra.contextid = c.id
              AND ra.userid = ue.userid
              AND ra.itemid = e.id
              AND ra.component = 'enrol_ilios'
              AND e.roleid = ra.roleid
         )
         WHERE ue.status = :useractive AND ra.id IS NULL";
        $params = [];
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
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
        $params = [];
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
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
        $params = [];
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $gm) {
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
        $params = [];
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ue) {
            groups_add_member($ue->groupid, $ue->userid, 'enrol_ilios', $ue->enrolid);
            $trace->output("adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname", 1);
        }
        $rs->close();

        $trace->output('...user enrolment synchronisation finished.');

        return 0;
    }

    /**
     * Update instance status.
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     * @throws Exception
     */
    public function update_status($instance, $newstatus) {
        parent::update_status($instance, $newstatus);

        $trace = new null_progress_trace();
        $this->sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended.
     *
     * @param stdClass $instance The course enrol instance.
     * @param stdClass $ue A user enrolment record.
     *
     * @return bool TRUE means that the current user may unenrol this user, FALSE otherwise.
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue): bool {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager The course enrolment manager.
     * @param stdClass $ue A user enrolment object.
     * @return array An array of user_enrolment_actions.
     * @throws Exception
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue): array {
        $actions = [];
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/ilios:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', ''),
                get_string('unenrol', 'enrol'),
                $url,
                ['class' => 'unenrollink', 'rel' => $ue->id]
            );
        }
        return $actions;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step The restore enrolments structure step.
     * @param stdClass $data The data object.
     * @param stdClass $course The course object.
     * @param int $oldid The old enrolment instance ID.
     * @throws Exception
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid): void {
        global $DB;

        if (!$step->get_task()->is_samesite()) {
            // No ilios restore from other sites.
            $step->set_mapping('enrol', $oldid, 0);
            return;
        }

        if (!empty($data->customint6)) {
            $data->customint6 = $step->get_mappingid('group', $data->customint6);
        }

        if ($data->roleid) {
            $instance = $DB->get_record(
                'enrol',
                [
                    'roleid' => $data->roleid,
                    'customint1' => $data->customint1,
                    'customchar1' => $data->customchar1,
                    'courseid' => $course->id,
                    'enrol' => $this->get_name(),
                ],
            );
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
            $instance = $DB->get_record(
                'enrol',
                [
                    'roleid' => $data->roleid,
                    'customint1' => $data->customint1,
                    'customerchar1' => $data->customchar1,
                    'courseid' => $course->id,
                    'enrol' => $this->get_name(),
                    ],
            );
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
     * @param restore_enrolments_structure_step $step The restore enrolment structure step.
     * @param stdClass $data The data object.
     * @param stdClass $instance The enrolment instance.
     * @param int $userid The user ID.
     * @param int $oldinstancestatus The old enrolment instance status.
     * @throws Exception
     */
    public function restore_user_enrolment(
        restore_enrolments_structure_step $step,
        $data,
        $instance,
        $userid,
        $oldinstancestatus
    ): void {
        global $DB;

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        // ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored
        // but without roles and suspended.

        if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Recursive get for learner group data with instructors info, to compensate for
     * something that the ILIOS API fails to do!
     *
     * @param  string $grouptype Singular noun of the group type, e.g. cohort, learnerGroup.
     * @param  string $groupid   The ID for the corresponding group type, e.g. cohort id, learner group id.
     *
     * @return mixed Returned by the ILIOS api in addition of populating
     *               the instructor array with correct ids, which is to
     *               iterate into offerings and ilmSessions and fetch the
     *               associated instructors and instructor groups. Should
     *               also iterate into subgroups.
     * @throws Exception
     */
    public function get_group_data($grouptype, $groupid) {
        $apiclient = $this->get_api_client();
        $accesstoken = $this->get_api_access_token();
        // Ilios API uses a plural noun, append an 's'.
        $group = $apiclient->get_by_id($accesstoken, $grouptype.'s', $groupid );

        if ($group && $grouptype === 'learnerGroup') {
            $group->instructors = $this->get_instructor_ids_from_group($grouptype, $groupid);
            asort($group->instructors);
        }

        return $group;
    }

    /**
     * Retrieves a list instructors for a given type of group (learner group or instructor group) and given group ID.

     * @param string $grouptype The group type (either 'instructorgroup' or 'learnergroup').
     * @param string $groupid The group ID.
     * @return array A list of user IDs.
     * @throws moodle_exception
     */
    private function get_instructor_ids_from_group($grouptype, $groupid): array {
        $apiclient = $this->get_api_client();
        $accesstoken = $this->get_api_access_token();

        // Ilios API uses a plural noun, append an 's'.
        $group = $apiclient->get_by_id($accesstoken, $grouptype.'s', $groupid);

        $instructorgroupids = [];
        $instructorids = [];

        // Get instructors/instructor-groups from the offerings that this learner group is being taught in.
        if (!empty($group->offerings)) {
            $offerings = $apiclient->get_by_ids($accesstoken, 'offerings', $group->offerings);

            foreach ($offerings as $offering) {
                if (empty($offering->instructors) && empty($offering->instructorGroups)) {
                    // No instructor AND no instructor groups have been set for this offering.
                    // Fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorids = array_merge($instructorids, $group->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $group->instructorGroups);
                } else {
                    // If there are instructors and/or instructor-groups set on the offering, then use these.
                    $instructorids = array_merge($instructorids, $offering->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $offering->instructorGroups);
                }
            }
        }

        // Get instructors/instructor-groups from the ilm sessions that this learner group is being taught in.
        // This is a rinse/repeat from offerings-related code above.
        if (!empty($group->ilmSessions)) {
            $ilms = $apiclient->get_by_ids($accesstoken, 'ilmSessions', $group->ilmSessions);

            foreach ($ilms as $ilm) {
                if (empty($ilm->instructors) && empty($ilm->instructorGroups)) {
                    // No instructor AND no instructor groups have been set for this offering.
                    // Fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorids = array_merge($instructorids, $group->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $group->instructorGroups);
                } else {
                    // If there are instructors and/or instructor-groups set on the offering, then use these.
                    $instructorids = array_merge($instructorids, $ilm->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $ilm->instructorGroups);
                }
            }
        }

        // Get instructors from sub-learner-groups.
        if (!empty($group->children)) {
            foreach ($group->children as $subgroupid) {
                $instructorids = array_merge(
                    $instructorids,
                    $this->get_instructor_ids_from_group('learnerGroup', $subgroupid)
                );
                // We don't care about instructor groups here,
                // we will merge instructor groups into the $instructorIds array later.
            }
        }

        // Next, get the ids of all instructors from the instructor-groups that we determined as relevant earlier.
        // But first let's de-dupe them.
        $instructorgroupids = array_unique($instructorgroupids);
        if (!empty($instructorgroupids)) {
            $instructorgroups = $apiclient->get_by_ids($accesstoken, 'instructorGroups', $instructorgroupids);
            foreach ($instructorgroups as $instructorgroup) {
                $instructorids = array_merge($instructorids, $instructorgroup->users);
            }
        }

        // Finally, we retrieve all the users that were identified as relevant instructors earlier.
        $instructorids = array_unique($instructorids);

        return $instructorids;
    }
}

/**
 * Prevents the removal of enrol roles.
 * Implements the <code>allow_group_member_remove</code> callback from the Group API.
 *
 * @param int $itemid The item ID.
 * @param int $groupid The group ID.
 * @param int $userid The user ID.
 * @return bool Always FALSE.
 */
function enrol_ilios_allow_group_member_remove($itemid, $groupid, $userid): bool {
    return false;
}
