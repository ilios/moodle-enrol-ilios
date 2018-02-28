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
 * Local stuff for ilios enrolment plugin.
 *
 * @package    enrol_ilios
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');


/**
 * Sync all ilios course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 * @throws \Exception
 */
function enrol_ilios_sync(progress_trace $trace, $courseid = NULL) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/group/lib.php");

    // Purge all roles if ilios sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('ilios')) {
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

    $plugin = enrol_get_plugin('ilios');
    $http   = $plugin->get_http_client();

    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

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
            $group = $plugin->getGroupData($synctype, $syncid);
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

            if (!empty($instance->customint2)) {
                $trace->output("Enrolling instructors to Course ID ".$instance->courseid." with Role ID ".$instance->roleid." through Ilios Sync ID ".$instance->id.".");
                $users = $http->getbyids('users', $group->instructors);
            } else {
                $trace->output("Enrolling students to Course ID ".$instance->courseid." with Role ID ".$instance->roleid." through Ilios Sync ID ".$instance->id.".");
                $users = $http->getbyids('users', $group->users);
            }

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
                    $status = ENROL_USER_ACTIVE;

                    $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));

                    // Continue if already enrolled with active status
                    if (!empty($ue) && $status === (int)$ue->status) {
                        continue;
                    }

                    // Enroll user
                    $plugin->enrol_user($instance, $userid, $instance->roleid, 0, 0, $status);
                    if ($status !== (int)$ue->status) {
                        $trace->output("changing enrollment status to '{$status}' from '{$ue->status}': userid $userid ==> courseid ".$instance->courseid, 1);
                    } else {
                        $trace->output("enrolling with $status status: userid $userid ==> courseid ".$instance->courseid, 1);
                    }
                }
            }

            // Unenrol as necessary.
            if (!empty($enrolleduserids)) {
                $sql = "SELECT ue.*
                              FROM {user_enrolments} ue
                             WHERE ue.enrolid = $instance->id
                               AND ue.userid NOT IN ( ".implode(",", $enrolleduserids)." )";

                $rs = $DB->get_recordset_sql($sql);
                foreach($rs as $ue) {
                    if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                        // Remove enrolment together with group membership, grades, preferences, etc.
                        $plugin->unenrol_user($instance, $ue->userid);
                        $trace->output("unenrolling: $ue->userid ==> ".$instance->courseid." via Ilios $synctype $syncid", 1);
                    } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
                        // Just disable and ignore any changes.
                        if ($ue->status != ENROL_USER_SUSPENDED) {
                            $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                            $context = context_course::instance($instance->courseid);
                            role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_ilios', 'itemid'=>$instance->id));
                            $trace->output("suspending and unsassigning all roles: userid ".$ue->userid." ==> courseid ".$instance->courseid, 1);
                        }
                    }
                }
                $rs->close();
            }
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

    return 0;
}
