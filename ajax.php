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
 * This file processes AJAX enrolment actions and returns JSON for the ilios plugin
 *
 * The general idea behind this file is that any errors should throw exceptions
 * which will be returned and acted upon by the calling AJAX script.
 *
 * @package    enrol_ilios
 * @copyright 2015 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require('../../config.php');
require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/enrol/ilios/locallib.php');
require_once($CFG->dirroot.'/group/lib.php');

// Must have the sesskey.
$id      = required_param('id', PARAM_INT); // course id
$action  = required_param('action', PARAM_ALPHANUMEXT);

$PAGE->set_url(new moodle_url('/enrol/ilios/ajax.php', array('id'=>$id, 'action'=>$action)));

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

if ($course->id == SITEID) {
    throw new moodle_exception('invalidcourse');
}

require_login($course);
require_capability('moodle/course:enrolreview', $context);
require_sesskey();

if (!enrol_is_enabled('ilios')) {
    // This should never happen, no need to invent new error strings.
    throw new enrol_ajax_exception('errorenrolilios');
}

echo $OUTPUT->header(); // Send headers.

$manager = new course_enrolment_manager($PAGE, $course);

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';

$enrol = enrol_get_plugin('ilios');

switch ($action) {
    case 'getassignable':
        $otheruserroles = optional_param('otherusers', false, PARAM_BOOL);
        $outcome->response = array_reverse($manager->get_assignable_roles($otheruserroles), true);
        break;
    case 'getdefaultiliosrole': //TODO: use in ajax UI MDL-24280
        $iliosenrol = enrol_get_plugin('ilios');
        $outcome->response = $iliosenrol->get_config('roleid');
        break;
    case 'getcohorts':
        require_capability('moodle/course:enrolconfig', $context);
        $offset = optional_param('offset', 0, PARAM_INT);
        $search  = optional_param('search', '', PARAM_RAW);
        $outcome->response = enrol_ilios_search_cohorts($manager, $offset, 25, $search);
        // Some browsers reorder collections by key.
        $outcome->response['cohorts'] = array_values($outcome->response['cohorts']);
        break;
    case 'getselectschooloptions':
        require_capability('moodle/course:enrolconfig', $context);
        $http = $enrol->get_http_client();
        $schools = $http->get('schools', '', array('title' => "ASC"));
        $schoolarray = array();
        foreach ($schools as $school) {
            $schoolarray["$school->id:$school->title"] = $school->title;
        }
        $outcome->response = $schoolarray;
        break;
    case 'getselectprogramoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $sid      = required_param('filterid', PARAM_INT); // school id
        $http = $enrol->get_http_client();
        $programs = $http->get('programs', array('owningSchool' => $sid, 'deleted' => false), array('title'=> "ASC"));
        $programarray = array();
        foreach ($programs as $program) {
            $programarray["$program->id:$program->shortTitle:$program->title"] = $program->title;
        }
        $outcome->response = $programarray;
        break;
    case 'getselectcohortoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $pid    = required_param('filterid', PARAM_INT);
        $http = $enrol->get_http_client();
        $programyears = $http->get('programYears',
                                              array("program" => $pid, "deleted" => false),
                                              array("startYear" => "ASC"));
        $programyeararray = array();
        $cohortoptions = array();
        foreach ($programyears as $progyear) {
            $programyeararray[] = $progyear->id;
        }

        if (!empty($programyeararray)) {
            $cohorts = $http->get('cohorts',
                                  array("programYear" => $programyeararray),
                                  array("title" => "ASC"));
            foreach ($cohorts as $cohort) {
                $cohortoptions["$cohort->id:$cohort->title"] = $cohort->title
                                                             .' ('.count($cohort->learnerGroups).')'
                                                             .' ('.count($cohort->users).')';
            }
        }
        $outcome->response = $cohortoptions;
        break;

    case 'getselectlearnergroupoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $cid      = required_param('filterid', PARAM_INT); // school id
        $http = $enrol->get_http_client();
        $learnergroups = $http->get('learnerGroups',
                                               array('cohort' => $cid, 'parent' => 'null'),
                                               array('title'=> "ASC"));
        $grouparray = array();
        foreach ($learnergroups as $group) {
            $grouparray["$group->id:$group->title"] = $group->title.
                                                    ' ('. count($group->children) .')'.
                                                    ' ('. count($group->users) .')';
        }
        $outcome->response = $grouparray;
        break;

    case 'getselectsubgroupoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $gid      = required_param('filterid', PARAM_INT); // group id
        $subgroupoptions = array();
        $http = $enrol->get_http_client();
        $subgroups = $http->get('learnerGroups',
                                           array("parent" => $gid),
                                           array("title" => "ASC"));
        foreach ($subgroups as $subgroup) {
            $subgroupoptions["$subgroup->id:$subgroup->title"] = $subgroup->title.
                                                               ' ('. count($subgroup->children) .')'.
                                                               ' ('. count($subgroup->users) .')';
            if (!empty($subgroup->children)) {
                $processchildren = function ($parent) use (&$processchildren,&$subgroupoptions,$http) {
                    $subgrps = $http->get('learnerGroups',
                                                     array( 'parent' => $parent->id),
                                                     array( 'title' => "ASC"));
                    foreach ($subgrps as $subgrp) {
                        $subgroupoptions["$subgrp->id:$parent->title / $subgrp->title"] = $parent->title.' / '.$subgrp->title.
                                                                                        ' ('. count($subgrp->children) .')'.
                                                                                        ' ('. count($subgrp->users) .')';
                        if (!empty($grp->children)) {
                            $processchildren($subgrp);
                        }
                    }
                };
                $processchildren($subgroup);
            }
        }
        $outcome->response = $subgroupoptions;
        break;

    case 'enrolilios':
        require_capability('moodle/course:enrolconfig', $context);
        require_capability('enrol/ilios:config', $context);
        $roleid = required_param('roleid', PARAM_INT);
        $cohortid = required_param('cohortid', PARAM_INT);

        $roles = $manager->get_assignable_roles();
        if (!enrol_ilios_can_view_cohort($cohortid) || !array_key_exists($roleid, $roles)) {
            throw new enrol_ajax_exception('errorenrolilios');
        }
        $enrol = enrol_get_plugin('ilios');
        $enrol->add_instance($manager->get_course(), array('customint1' => $cohortid, 'roleid' => $roleid));
        $trace = new null_progress_trace();
        enrol_ilios_sync($trace, $manager->get_course()->id);
        $trace->finished();
        break;
    case 'enroliliosusers':
        //TODO: this should be moved to enrol_manual, see MDL-35618.
        require_capability('enrol/manual:enrol', $context);
        $roleid = required_param('roleid', PARAM_INT);
        $cohortid = required_param('cohortid', PARAM_INT);

        $roles = $manager->get_assignable_roles();
        if (!enrol_ilios_can_view_cohort($cohortid) || !array_key_exists($roleid, $roles)) {
            throw new enrol_ajax_exception('errorenrolilios');
        }

        $result = enrol_ilios_enrol_all_users($manager, $cohortid, $roleid);
        if ($result === false) {
            throw new enrol_ajax_exception('errorenroliliosusers');
        }

        $outcome->success = true;
        $outcome->response->users = $result;
        $outcome->response->title = get_string('success');
        $outcome->response->message = get_string('enrollednewusers', 'enrol', $result);
        $outcome->response->yesLabel = get_string('ok');
        break;
    default:
        throw new enrol_ajax_exception('unknowajaxaction');
}

echo json_encode($outcome);
die();
