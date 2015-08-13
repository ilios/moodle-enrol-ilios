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
 * @copyright 2015 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');


/**
 * Sync all ilios course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
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
    $iliosusers = array(); // cache

    $plugin = enrol_get_plugin('ilios');
    $http   = $plugin->get_http_client();

    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
    $moodleusersyncfield = 'idnumber';
    $iliosusersyncfield = 'ucUid';
    // $moodleusersyncfield = 'id';
    // $iliosusersyncfield = 'id';

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

        $groups = $http->get($synctype.'s', array( "id" => $syncid ));

        // TODO: How to handle deleted group/cohort
        if (!empty($groups) && is_array($groups)) {
            $group = $groups[0];

            if (!empty($group->users)) {
                $users = $http->getbyids('users', $group->users);
                $userids = array();
                foreach ($users as $user) {
                    if (!isset($iliosusers[$user->id])) {
                        $iliosusers[$user->id] = null;
                        if (!empty($user->$iliosusersyncfield)) {
                            $urec = $DB->get_record('user', array("$moodleusersyncfield" => $user->$iliosusersyncfield));
                            if (!empty($urec)) {
                                $iliosusers[$user->id] = array( 'id' => $urec->id,
                                                                'syncfield' => $urec->$moodleusersyncfield );
                                $userids[] = $urec->id;
                            }
                        }
                    }

                    if ($iliosusers[$user->id] === null) {
                        if (!empty($user->$iliosusersyncfield)) {
                            $trace->output("skipping: Cannot find $iliosusersyncfield ".$user->$iliosusersyncfield." that matches Moodle user field $moodleusersyncfield.", 1);
                        } else {
                            $trace->output("skipping: Ilios user ".$user->id." does not have a $iliosusersyncfield field.", 1);
                        }
                    } else {
                        $userid = $iliosusers[$user->id]['id'];
                        $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));

                        if (!empty($ue) && isset($ue->status)) {
                            if ($ue->status == ENROL_USER_SUSPENDED) {
                                $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
                                $trace->output("unsuspending: userid $userid ==> courseid ".$instance->courseid." via Ilios $synctype $syncid", 1);
                            }
                        } else {
                            $plugin->enrol_user($instance, $userid);
                            $trace->output("enrolling: userid $userid ==> courseid ".$instance->courseid." via Ilios $synctype $syncid", 1);
                        }
                    }
                }

                // Unenrol as necessary.
                if (!empty($userids)) {
                    $sql = "SELECT ue.*
                              FROM {user_enrolments} ue
                             WHERE ue.enrolid = $instance->id
                               AND ue.userid NOT IN ( ".implode(",", $userids)." )";

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

/**
 * Enrols all of the users in a cohort through a manual plugin instance.
 *
 * In order for this to succeed the course must contain a valid manual
 * enrolment plugin instance that the user has permission to enrol users through.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $cohortid
 * @param int $roleid
 * @return int
 */

// TOOD: This is being called by ajax.php.  Update this to take learner group id
function enrol_ilios_enrol_all_users(course_enrolment_manager $manager, $cohortid, $roleid) {
    global $DB;
    $context = $manager->get_context();
    require_capability('moodle/course:enrolconfig', $context);

    $instance = false;
    $instances = $manager->get_enrolment_instances();
    foreach ($instances as $i) {
        if ($i->enrol == 'manual') {
            $instance = $i;
            break;
        }
    }
    $plugin = enrol_get_plugin('manual');
    if (!$instance || !$plugin || !$plugin->allow_enrol($instance) || !has_capability('enrol/'.$plugin->get_name().':enrol', $context)) {
        return false;
    }
    $sql = "SELECT com.userid
              FROM {cohort_members} com
         LEFT JOIN (
                SELECT *
                  FROM {user_enrolments} ue
                 WHERE ue.enrolid = :enrolid
                 ) ue ON ue.userid=com.userid
             WHERE com.cohortid = :cohortid AND ue.id IS NULL";
    $params = array('cohortid' => $cohortid, 'enrolid' => $instance->id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $count = 0;
    foreach ($rs as $user) {
        $count++;
        $plugin->enrol_user($instance, $user->userid, $roleid);
    }
    $rs->close();
    return $count;
}

// DELETE: Not being called anywhere
// /**
//  * Gets all the cohorts the user is able to view.
//  *
//  * @global moodle_database $DB
//  * @param course_enrolment_manager $manager
//  * @return array
//  */
// function enrol_ilios_get_cohorts(course_enrolment_manager $manager) {
//     global $DB;
//     $context = $manager->get_context();
//     $cohorts = array();
//     $instances = $manager->get_enrolment_instances();
//     $enrolled = array();
//     foreach ($instances as $instance) {
//         if ($instance->enrol == 'ilios') {
//             $enrolled[] = $instance->customint1;
//         }
//     }
//     list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());
//     $sql = "SELECT id, name, idnumber, contextid
//               FROM {cohort}
//              WHERE contextid $sqlparents
//           ORDER BY name ASC, idnumber ASC";
//     $rs = $DB->get_recordset_sql($sql, $params);
//     foreach ($rs as $c) {
//         $context = context::instance_by_id($c->contextid);
//         if (!has_capability('moodle/cohort:view', $context)) {
//             continue;
//         }
//         $cohorts[$c->id] = array(
//             'cohortid'=>$c->id,
//             'name'=>format_string($c->name, true, array('context'=>context::instance_by_id($c->contextid))),
//             'users'=>$DB->count_records('cohort_members', array('cohortid'=>$c->id)),
//             'enrolled'=>in_array($c->id, $enrolled)
//         );
//     }
//     $rs->close();
//     return $cohorts;
// }

/**
 * Check if cohort exists and user is allowed to enrol it.
 *
 * @global moodle_database $DB
 * @param int $cohortid Ilios enrolment ID
 * @return boolean
 */
// TODO: Being called by ajax.php.  Not really needed.
function enrol_ilios_can_view_cohort($cohortid) {
    global $DB;
    $ilios = $DB->get_record('ilios', array('id' => $cohortid), 'id, contextid');
    if ($ilios) {
        $context = context::instance_by_id($ilios->contextid);
        if (has_capability('moodle/cohort:view', $context)) {
            return true;
        }
    }
    return false;
}

/**
 * Gets cohorts the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $offset limit output from
 * @param int $limit items to output per load
 * @param string $search search string
 * @return array    Array(more => bool, offset => int, cohorts => array)
 */
// TODO: Update to search learner groups
function enrol_ilios_search_cohorts(course_enrolment_manager $manager, $offset = 0, $limit = 25, $search = '') {
    global $DB;
    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    $enrolled = array();
    foreach ($instances as $instance) {
        if ($instance->enrol == 'ilios') {
            $enrolled[] = $instance->customint1;
        }
    }

    list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());

    // Add some additional sensible conditions.
    $tests = array('contextid ' . $sqlparents);

    // Modify the query to perform the search if required.
    if (!empty($search)) {
        $conditions = array(
            'name',
            'idnumber',
            'description'
        );
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key=>$condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $sql = "SELECT id, name, idnumber, contextid, description
              FROM {cohort}
             WHERE $wherecondition
          ORDER BY name ASC, idnumber ASC";
    $rs = $DB->get_recordset_sql($sql, $params, $offset);

    // Produce the output respecting parameters.
    foreach ($rs as $c) {
        // Track offset.
        $offset++;
        // Check capabilities.
        $context = context::instance_by_id($c->contextid);
        if (!has_capability('moodle/cohort:view', $context)) {
            continue;
        }
        if ($limit === 0) {
            // We have reached the required number of items and know that there are more, exit now.
            $offset--;
            break;
        }
        $cohorts[$c->id] = array(
            'cohortid' => $c->id,
            'name'     => shorten_text(format_string($c->name, true, array('context'=>context::instance_by_id($c->contextid))), 35),
            'users'    => $DB->count_records('cohort_members', array('cohortid'=>$c->id)),
            'enrolled' => in_array($c->id, $enrolled)
        );
        // Count items.
        $limit--;
    }
    $rs->close();
    return array('more' => !(bool)$limit, 'offset' => $offset, 'cohorts' => $cohorts);
}
