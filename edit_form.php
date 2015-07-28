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
 * Adds instance form
 *
 * @package    enrol_ilios
 * @copyright  2015 Carson Tam {@email carson.tam@ucsf.edu }
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once("lib.php");

class enrol_ilios_edit_form extends moodleform {

    private $httpIlios = null;

    function definition() {
        global $CFG, $DB, $PAGE;

        $mform  = $this->_form;
        $PAGE->requires->yui_module('moodle-enrol_ilios-groupchoosers', 'M.enrol_ilios.init_groupchoosers',
                array(array('formid' => $mform->getAttribute('id'))));

        list($instance, $plugin, $course) = $this->_customdata;
        $coursecontext = context_course::instance($course->id);

        // $enrol = enrol_get_plugin('ilios');
        $enrol = $plugin;
        $iliosarray = array();

        if (!isset($CFG->ilios_http)) {
            // echo "<pre>"; debug_print_backtrace(); echo "</pre>";
            $CFG->ilios_http = new ilios_client($enrol->get_config('host_url'),
                                                $enrol->get_config('userid'),
                                                $enrol->get_config('secret'),
                                                $enrol->get_config('apikey'));
        }
        if ($this->httpIlios === null) {
            $this->httpIlios = $CFG->ilios_http;
        }

        $groups = array(0 => get_string('none'));
        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context'=>$coursecontext));
        }

        $mform->addElement('header','general', get_string('pluginname', 'enrol_ilios'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_ilios'), $options);

        $http = $this->httpIlios;

        $schools = $http->get('schools');
        // Check $schools: may not return an object.
        if ($schools === null) { // no connection to the server
            $schooloptions = array('' => get_string('error'));
        } else {
            $schooloptions = array('' => get_string('choosedots'));

            foreach ($schools as $school) {
                if ($school->deleted) {
                    $iliosarray['schools'][$school->id]['title'] = $schooloptions[ $school->id ] = '*'.$school->title;
                } else {
                    $iliosarray['schools'][$school->id]['title'] = $schooloptions[ $school->id ] = $school->title;
                }
            }
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            // TODO: Worry about getting the these values later
            // $formoptions = json_decode($instance->customtext1);
            // $mform->setConstant('selectschool', $formoptions->school->id);
            // $mform->hardFreeze('selectschool', $formoptions->school->id);
        } else {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            $mform->addRule('selectschool', get_string('required'), 'required', null, 'client');
            $mform->registerNoSubmitButton('updateschooloptions');
            $mform->addElement('submit', 'updateschooloptions', get_string('schooloptionsupdate', 'enrol_ilios'));
        }

        $programs = array('' => get_string('choosedots'));
        if ($instance->id) {
            $mform->addElement('select', 'customint2', get_string('program', 'enrol_ilios'), $programs);
            $mform->setConstant('customint2', $instance->customint2);
            $mform->hardFreeze('customint2', $instance->customint2);

        } else {
            $mform->addElement('select', 'customint2', get_string('program', 'enrol_ilios'), $programs);
            $mform->addRule('customint2', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('customint2', 'program', 'enrol_ilios');
            $mform->disabledIf('customint2', 'selectschool', 'eq', '');
            $mform->registerNoSubmitButton('updateprogramoptions');
            $mform->addElement('submit', 'updateprogramoptions', get_string('programoptionsupdate', 'enrol_ilios'));
        }

        $programyears = array('' => get_string('choosedots'));
        if ($instance->id) {
            $mform->addElement('select', 'customint3', get_string('programyear', 'enrol_ilios'), $programyears);
            $mform->setConstant('customint3', $instance->customint3);
            $mform->hardFreeze('customint3', $instance->customint3);

        } else {
            $mform->addElement('select', 'customint3', get_string('programyear', 'enrol_ilios'), $programyears);
            $mform->addRule('customint3', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('customint3', 'customint2', 'eq', '');
            $mform->registerNoSubmitButton('updateprogramyearoptions');
            $mform->addElement('submit', 'updateprogramyearoptions', get_string('programyearoptionsupdate', 'enrol_ilios'));
        }

        $learnergroups = array('' => get_string('choosedots'));
        if ($instance->id) {
            $mform->addElement('select', 'customchar1', get_string('group', 'enrol_ilios'), $learnergroups);
            $mform->setConstant('customchar1', $instance->customchar1);
            $mform->hardFreeze('customchar1', $instance->customchar1);

        } else {
            $mform->addElement('select', 'customchar1', get_string('group', 'enrol_ilios'), $learnergroups);
            $mform->addRule('customchar1', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('customchar1', 'customint3', 'eq', '');
            $mform->registerNoSubmitButton('updategroupoptions');
            $mform->addElement('submit', 'updategroupoptions', get_string('groupoptionsupdate', 'enrol_ilios'));
        }

        $subgroups = array('' => get_string('none'));
        if ($instance->id) {
            $mform->addElement('select', 'customint5', get_string('subgroup', 'enrol_ilios'), $subgroups);
            $mform->setConstant('customint5', $instance->customint5);
            $mform->hardFreeze('customint5', $instance->customint5);

        } else {
            $mform->addElement('select', 'customint5', get_string('subgroup', 'enrol_ilios'), $subgroups);
            //$mform->addRule('customint5', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('customint5', 'customchar1', 'eq', '');
        }

        // if ($instance->id) {
        //     if ($school = $DB->get_record('cohort', array('id'=>$instance->customint1))) {
        //         $cohorts = array($instance->customint1=>format_string($school->name, true, array('context'=>context::instance_by_id($school->contextid))));
        //     } else {
        //         $cohorts = array($instance->customint1=>get_string('error'));
        //     }
        //     $mform->addElement('select', 'customint1', get_string('group', 'enrol_ilios'), $cohorts);
        //     $mform->setConstant('customint1', $instance->customint1);
        //     $mform->hardFreeze('customint1', $instance->customint1);

        // } else {
        //     $cohorts = array('' => get_string('choosedots'));
        //     list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
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
        //         $cohorts[$c->id] = format_string($c->name);
        //     }
        //     $rs->close();
        //     $mform->addElement('select', 'customint1', get_string('group', 'enrol_ilios'), $cohorts);
        //     $mform->addRule('customint1', get_string('required'), 'required', null, 'client');
        // }

        $roles = get_assignable_roles($coursecontext);
        $roles[0] = get_string('none');
        $roles = array_reverse($roles, true); // Descending default sortorder.
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_ilios'), $roles);
        $mform->setDefault('roleid', $enrol->get_config('roleid'));
        if ($instance->id and !isset($roles[$instance->roleid])) {
            if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $roles = role_fix_names($roles, $coursecontext, ROLENAME_ALIAS, true);
                $roles[$instance->roleid] = role_get_name($role, $coursecontext);
            } else {
                $roles[$instance->roleid] = get_string('error');
            }
        }
        $mform->addElement('select', 'customint6', get_string('addgroup', 'enrol_ilios'), $groups);

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        if ($instance->id) {
            $this->add_action_buttons(true);
        } else {
            $this->add_action_buttons(true, get_string('addinstance', 'enrol'));
        }

        $this->set_data($instance);
    }

    function definition_after_data() {
        global $DB;
        $mform = $this->_form;
        $enrol = enrol_get_plugin('ilios');
        $http = $this->httpIlios;

        // echo "<pre>"; debug_print_backtrace(); echo "</pre>";

        $schoolid = $mform->getElementValue('selectschool');
        $programid = $mform->getElementValue('customint2');
        $programyearid = $mform->getElementValue('customint3');
        $cohortid = $mform->getElementValue('customchar1');

        if ((is_array($schoolid) && !empty($schoolid[0])) &&
            (is_array($programid) && !empty($programid[0]))) {
            $sid = $schoolid[0];
            $prog_el =& $mform->getElement('customint2');
            $programoptions = array();

            $programs = $http->get('programs',
                                   array( 'owningSchool' => $sid, 'deleted' => false ),
                                   array( 'title'=>'ASC'));

            foreach ($programs as $program) {
                $programoptions[$program->id] = $program->title;
            }
            $prog_el->load($programoptions);
        }

        if ((is_array($programid) && !empty($programid[0])) &&
            (is_array($programyearid) && !empty($programyearid[0]))) {
            $pid = $programid[0];
            $prog_el =& $mform->getElement('customint3');
            $programyearoptions = array();

            $programyears = $http->get('programYears',
                                       array("program" => $pid, "deleted" => false),
                                       array("startYear" => 'ASC'));
            $cohorts = $http->get('cohorts');

            foreach ($programyears as $year) {
                $programyearoptions[$year->id] = $year->startYear;
            }
            foreach ($cohorts as $cohort) {
                if (isset($programyearoptions[$cohort->programYear])) {
                    $programyearoptions[$cohort->programYear] = $cohort->title;
                }
            }
            $prog_el->load($programyearoptions);
        }

        if ((is_array($programyearid) && !empty($programyearid[0])) &&
            (is_array($cohortid) && !empty($cohortid[0]))) {
            $pid = $programyearid[0];
            $prog_el =& $mform->getElement('customchar1');
            $cohortoptions = array();

            $cohorts = $http->get('cohorts',
                                  array("programYear" => $pid),
                                  array("title" => "ASC"));
            foreach ($cohorts as $cohort) {
                $cohortoptions['cohort:'.$cohort->id] = $cohort->title . ' ('. count($cohort->users) .')';
                $learnergroups = $http->get('learnerGroups',
                                            array('cohort' => $cohort->id, 'parent' => 'null'),
                                            array('title' => "ASC"));
                if (is_array($learnergroups)) {
                    foreach ($learnergroups as $group) {
                        $cohortoptions['learnerGroup:'.$group->id] = $cohort->title . ' / ' . $group->title. ' ('. count($group->users) .')';
                    }
                }
            }
            $prog_el->load($cohortoptions);
        }

        if (is_array($cohortid) && !empty($cohortid[0])) {
            list($gtype, $gid) = explode(':', $cohortid[0] );

            $prog_el =& $mform->getElement('customint5');
            $groupoptions = array();

            if ($gtype == 'learnerGroup') {
                $groups = $http->get('learnerGroups',
                                     array("parent" => $gid),
                                     array("title" => "ASC"));
                foreach ($groups as $group) {
                    $groupoptions[$group->id] = $group->title. ' ('. count($group->users) .')';
                    if (!empty($group->children)) {
                        $processchildren = function ($parent) use (&$processchildren,&$groupoptions) {
                            foreach( $parent->children as $gid) {
                                $subgrp = $http->get('learnerGroup',
                                                     array( 'id' => $gid ),
                                                     array( 'title' => "ASC"));
                                $groupoptions[$subgrp->id] = $parent->title.' / '.$subgrp->title. ' ('. count($subgrp->users) .')';
                                if (!empty($grp->children)) {
                                    $processchildren($subgrp);
                                }
                            }
                        };
                        $processchildren($group);
                    }
                }
                $prog_el->load($groupoptions);
            }
        }
    }

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        //// Check for duplicates!!!  We should check this too.
        $params = array('roleid'=>$data['roleid'], 'customint1'=>$data['selectschool'], 'courseid'=>$data['courseid'], 'id'=>$data['id']);
        if ($DB->record_exists_select('enrol', "roleid = :roleid AND customint1 = :customint1 AND courseid = :courseid AND enrol = 'ilios' AND id <> :id", $params)) {
            $errors['roleid'] = get_string('instanceexists', 'enrol_ilios');
        }

        return $errors;
    }
}
