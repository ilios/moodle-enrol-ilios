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
 * Adds new instance of enrol_ilios to specified course.
 *
 * @package    enrol_ilios
 * @copyright  2015 Carson Tam {@email carson.tam@ucsf.edu}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/ilios/edit_form.php");
require_once("$CFG->dirroot/enrol/ilios/locallib.php");
require_once("$CFG->dirroot/group/lib.php");

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/ilios:config', $context);

$PAGE->set_url('/enrol/ilios/edit.php', array('courseid'=>$course->id, 'id'=>$instanceid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('ilios')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('ilios');

if ($instanceid) {
    $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'ilios', 'id'=>$instanceid), '*', MUST_EXIST);

} else {
    // No instance yet, we have to add new instance.
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id         = null;
    $instance->courseid   = $course->id;
    $instance->enrol      = 'ilios';
    $instance->customint1 = ''; // School id.
    $instance->customint2 = ''; // Program id.
    $instance->customint3 = ''; // Program Year id.
    $instance->customchar1 = ''; // Cohort / group id.
    $instance->customint5 = ''; // Sub group id.
    $instance->customint6 = 0;  // role id.
    // Let's try something more streamline:
    // customint1 => cohort / group id,
    // customchar1 => 'cohort / group',
    // customtext1 => '{ school: { id: 1, name: 'Medicine' }, program: ... }';

}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}


$mform = new enrol_ilios_edit_form(null, array($instance, $enrol, $course));

if ($mform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $mform->get_data()) {
    if ($data->id) {
        // NOTE: no cohort changes here!!!
        if ($data->roleid != $instance->roleid) {
            // The sync script can only add roles, for perf reasons it does not modify them.
            role_unassign_all(array('contextid'=>$context->id, 'roleid'=>$instance->roleid, 'component'=>'enrol_ilios', 'itemid'=>$instance->id));
        }
        $instance->name         = $data->name;
        $instance->status       = $data->status;
        $instance->roleid       = $data->roleid;
        $instance->customint1   = $data->selectschool;
        $instance->customint2   = $data->customint2;
        $instance->customint3   = $data->customint3;
        $instance->customchar1  = $data->customchar1;
        $instance->customint5   = $data->customint5;
        $instance->customint6   = $data->customint6;
        $instance->timemodified = time();
        $DB->update_record('enrol', $instance);
    }  else {
        $enrol->add_instance($course, array('name'=>$data->name, 'status'=>$data->status,
                                            'customint1'=>$data->selectschool,
                                            'customint2'=>$data->customint2,
                                            'customint3'=>$data->customint3,
                                            'customchar1'=>$data->customchar1,
                                            'customint5'=>$data->customint5,
                                            'roleid'=>$data->roleid, 'customint6'=>$data->customint6));
    }
    $trace = new null_progress_trace();
    enrol_ilios_sync($trace, $course->id);
    $trace->finished();
    redirect($returnurl);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_ilios'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
