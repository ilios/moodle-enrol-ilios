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
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require('../../config.php');
require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/group/lib.php');

// Must have the sesskey.
$id      = required_param('id', PARAM_INT); // course id
$action  = required_param('action', PARAM_ALPHANUMEXT);

$PAGE->set_url(new moodle_url('/enrol/ilios/ajax.php', ['id' => $id, 'action' => $action]));

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
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

/** @var enrol_ilios_plugin $enrol */
$enrol = enrol_get_plugin('ilios');
$apiclient = $enrol->get_api_client();
$accesstoken = $enrol->get_api_access_token();

switch ($action) {
    case 'getselectschooloptions':
        require_capability('moodle/course:enrolconfig', $context);
        $schools = $apiclient->get($accesstoken, 'schools', '', ['title' => "ASC"]);
        $schoolarray = [];
        foreach ($schools as $school) {
            $schoolarray["$school->id:$school->title"] = $school->title;
        }
        $outcome->response = $schoolarray;
        break;

    case 'getselectprogramoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $sid = required_param('filterid', PARAM_INT); // school id
        $programs = [];
        $programs = $apiclient->get($accesstoken, 'programs', ['school' => $sid], ['title' => "ASC"]);
        $programarray = [];
        foreach ($programs as $program) {
            $key = $program->id;
            foreach (['shortTitle', 'title'] as $attr) {
                $key .= ':';
                if (property_exists($program, $attr)) {
                    $key .= $program->$attr;
                }
            }
            $programarray[$key] = $program->title;
        }
        $outcome->response = $programarray;
        break;

    case 'getselectcohortoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $pid = required_param('filterid', PARAM_INT);
        $programyears = $apiclient->get(
                $accesstoken,
                'programYears',
                ["program" => $pid],
                ["startYear" => "ASC"]
        );
        $programyeararray = [];
        $cohortoptions = [];
        foreach ($programyears as $progyear) {
            $programyeararray[] = $progyear->id;
        }

        if (!empty($programyeararray)) {
            $cohorts = $apiclient->get(
                    $accesstoken,
                    'cohorts',
                    ["programYear" => $programyeararray],
                    ["title" => "ASC"]
            );
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
        $cid = required_param('filterid', PARAM_INT);    // cohort id
        $usertype = optional_param('usertype', 0, PARAM_INT); // learner or instructor
        $learnergroups = $apiclient->get(
                $accesstoken,
                'learnerGroups',
                ['cohort' => $cid, 'parent' => 'null'],
                ['title' => "ASC"]
        );
        $grouparray = [];
        foreach ($learnergroups as $group) {
            $grouparray["$group->id:$group->title"] = $group->title.
                                                    ' ('. count($group->children) .')';
            $grouparray["$group->id:$group->title"] .= ' ('. count($group->users) .')';
        }
        $outcome->response = $grouparray;
        break;

    case 'getselectsubgroupoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $gid = required_param('filterid', PARAM_INT);    // group id
        $usertype = optional_param('usertype', 0, PARAM_INT); // learner or instructor
        $subgroupoptions = [];
        $subgroups = $apiclient->get(
                $accesstoken,
                'learnerGroups',
                ["parent" => $gid],
                ["title" => "ASC"]
        );
        foreach ($subgroups as $subgroup) {
            $subgroupoptions["$subgroup->id:$subgroup->title"] = $subgroup->title.
                                                               ' ('. count($subgroup->children) .')';
            $subgroupoptions["$subgroup->id:$subgroup->title"] .= ' ('. count($subgroup->users) .')';

            if (!empty($subgroup->children)) {
                $processchildren = function ($parent) use (&$processchildren, &$subgroupoptions, $apiclient, $accesstoken) {
                    $subgrps = $apiclient->get(
                            $accesstoken,
                            'learnerGroups',
                            [ 'parent' => $parent->id],
                            [ 'title' => "ASC"]
                    );
                    foreach ($subgrps as $subgrp) {
                        $subgroupoptions["$subgrp->id:$parent->title / $subgrp->title"] = $parent->title.' / '.$subgrp->title.
                                                                                        ' ('. count($subgrp->children) .')';
                        $subgroupoptions["$subgrp->id:$parent->title / $subgrp->title"] .= ' ('. count($subgrp->users) .')';

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

    case 'getselectinstructorgroupoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $gid = required_param('filterid', PARAM_INT); // group id
        $instructorgroupoptions = [];
        $learnergroup = $apiclient->get_by_id($accesstoken, 'learnerGroups', $gid);
        if (!empty($learnergroup->instructorGroups)) {
            $instructorgroups = $apiclient->get(
                    $accesstoken,
                    'instructorGroups',
                    // array("id" => $learnergroup->instructorGroups),
                     '',
                    ["title" => "ASC"]
            );
            foreach ($instructorgroups as $instructorgroup) {
                $instructorgroupoptions["$instructorgroup->id:$instructorgroup->title"] = $instructorgroup->title.
                                                                                        ' ('. count($instructorgroup->users) .')';
            }
        }

        $outcome->response = $instructorgroupoptions;
        break;

    default:
        throw new enrol_ajax_exception('unknowajaxaction');
}

echo json_encode($outcome);
die();
