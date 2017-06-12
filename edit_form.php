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
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once("lib.php");

class enrol_ilios_edit_form extends moodleform {

    function definition() {
        global $CFG, $DB, $PAGE;

        $mform  = $this->_form;
        list($instance, $plugin, $course) = $this->_customdata;
        $coursecontext = context_course::instance($course->id);

        $PAGE->requires->yui_module('moodle-enrol_ilios-groupchoosers', 'M.enrol_ilios.init_groupchoosers',
                                    array(array('formid' => $mform->getAttribute('id'), 'courseid' => $course->id)));

        $enrol = $plugin;
        $http  = $plugin->get_http_client();

        $mform->addElement('header','general', get_string('pluginname', 'enrol_ilios'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_ilios'), $options);


        $usertypes = array(get_string('learner', 'enrol_ilios'), get_string('instructor', 'enrol_ilios'));
        $schooloptions = array('' => get_string('choosedots'));
        $programoptions = array('' => get_string('choosedots'));
        $cohortoptions = array('' => get_string('choosedots'));
        $learnergroupoptions = array('' => get_string('none'));
        $subgroupoptions = array('' => get_string('none'));
        $instructorgroupoptions = array('' => get_string('none'));

        if ($instance->id) {
            $synctype = $instance->customchar1;
            $syncid = $instance->customint1;
            $instance->customint2 = isset($instance->customint2) ? $instance->customint2 : 0;
            $syncinfo = json_decode($instance->customtext1);

            $instance->schoolid = $syncinfo->school->id;
            $school = $http->getbyid('schools', $instance->schoolid);
            $instance->selectschoolindex = "$instance->schoolid:$school->title";
            $schooloptions = array( $instance->selectschoolindex => $school->title );

            $instance->programid = $syncinfo->program->id;
            $program = $http->getbyid('programs', $instance->programid);
            $instance->selectprogramindex = "$instance->programid:$program->shortTitle:$program->title";
            $programoptions = array( $instance->selectprogramindex => $program->title );

            $instance->cohortid = $syncinfo->cohort->id;
            $cohort = $http->getbyid('cohorts', $instance->cohortid);
            $instance->selectcohortindex = "$instance->cohortid:$cohort->title";
            $cohortoptions = array( $instance->selectcohortindex =>
                                    $cohort->title
                                    .' ('.count($cohort->learnerGroups).')'
                                    .' ('.count($cohort->users).')');

            $instance->learnergroupid = '';
            $instance->selectlearnergroupindex = '';
            $instance->subgroupid = '';
            $instance->selectsubgroupindex = '';

            if ($synctype == 'learnerGroup') {
                $instance->learnergroupid = $syncid;
                if (!empty($instance->customint2)) {
                    $group = $enrol->getGroupData('learnerGroup', $instance->learnergroupid);
                } else {
                    $group = $http->getbyid('learnerGroups', $instance->learnergroupid);
                }
                $instance->selectlearnergroupindex = "$instance->learnergroupid:$group->title";
                $grouptitle = $group->title.
                            ' ('. count($group->children) .')';
                $grouptitle .= ' ('. count($group->users) .')';
                // $grouptitle .= (empty($instance->customint2)) ? ' ('. count($group->users) .')' : ' ('. count($group->instructors) .')';
                $learnergroupoptions = array($instance->selectlearnergroupindex => $grouptitle);

                if (!empty($group->parent)) {
                    $processparents = function ($child) use (&$processparents,
                                                             &$learnergroupoptions,
                                                             &$grouptitle,
                                                             &$instance,
                                                             $http) {
                        $parentgroup = $http->getbyid('learnerGroups', $child->parent);
                        $instance->learnergroupid = $parentgroup->id;
                        $instance->selectlearnergroupindex = "$instance->learnergroupid:$parentgroup->title";
                        $learnergroupoptions = array( "$instance->learnergroupid:$parentgroup->title" => $parentgroup->title);
                        if (!empty($parentgroup->parent)){
                            $grouptitle = $parentgroup->title . ' / '. $grouptitle;
                            $processparents($parentgroup);
                        }
                    };
                    $processparents($group);
                    $instance->subgroupid = $group->id;
                    $instance->selectsubgroupindex = "$group->id:$group->title";
                    $subgroupoptions = array($instance->selectsubgroupindex => $grouptitle);
                }
            }
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectusertype', get_string('selectusertype', 'enrol_ilios'), $usertypes);
            $mform->setConstant('selectusertype', $instance->customint2);
            $mform->hardFreeze('selectusertype', $instance->customint2);
        } else {
            $mform->addElement('select', 'selectusertype', get_string('selectusertype', 'enrol_ilios'), $usertypes);
            // $mform->addRule('selectusertype', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('selectusertype', 'selectusertype', 'enrol_ilios');
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            $mform->setConstant('selectschool', $instance->selectschoolindex);
            $mform->hardFreeze('selectschool', $instance->selectschoolindex);
        } else {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            $mform->addRule('selectschool', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('selectschool', 'school', 'enrol_ilios');
            $mform->registerNoSubmitButton('updateschooloptions');
            $mform->addElement('submit', 'updateschooloptions', get_string('schooloptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectprogram', get_string('program', 'enrol_ilios'), $programoptions);
            $mform->setConstant('selectprogram', $instance->selectprogramindex);
            $mform->hardFreeze('selectprogram', $instance->selectprogramindex);

        } else {
            $mform->addElement('select', 'selectprogram', get_string('program', 'enrol_ilios'), $programoptions);
            $mform->addRule('selectprogram', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('selectprogram', 'program', 'enrol_ilios');
            $mform->disabledIf('selectprogram', 'selectschool', 'eq', '');
            $mform->registerNoSubmitButton('updateprogramoptions');
            $mform->addElement('submit', 'updateprogramoptions', get_string('programoptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectcohort', get_string('cohort', 'enrol_ilios'), $cohortoptions);
            $mform->setConstant('selectcohort', $instance->selectcohortindex);
            $mform->hardFreeze('selectcohort', $instance->selectcohortindex);

        } else {
            $mform->addElement('select', 'selectcohort', get_string('cohort', 'enrol_ilios'), $cohortoptions);
            $mform->addRule('selectcohort', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('selectcohort', 'cohort', 'enrol_ilios');
            $mform->disabledIf('selectcohort', 'selectprogram', 'eq', '');
            $mform->registerNoSubmitButton('updatecohortoptions');
            $mform->addElement('submit', 'updatecohortoptions', get_string('cohortoptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectlearnergroup', get_string('learnergroup', 'enrol_ilios'), $learnergroupoptions);
            $mform->setConstant('selectlearnergroup', $instance->selectlearnergroupindex);
            $mform->hardFreeze('selectlearnergroup', $instance->selectlearnergroupindex);

        } else {
            $mform->addElement('select', 'selectlearnergroup', get_string('learnergroup', 'enrol_ilios'), $learnergroupoptions);
            // $mform->addRule('selectlearnergroup', get_string('required'), 'required', null, 'client');
            $mform->addHelpButton('selectlearnergroup', 'learnergroup', 'enrol_ilios');
            $mform->disabledIf('selectlearnergroup', 'selectcohort', 'eq', '');
            $mform->registerNoSubmitButton('updatelearnergroupoptions');
            $mform->addElement('submit', 'updatelearnergroupoptions', get_string('learnergroupoptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectsubgroup', get_string('subgroup', 'enrol_ilios'), $subgroupoptions);
            $mform->setConstant('selectsubgroup', $instance->selectsubgroupindex);
            $mform->hardFreeze('selectsubgroup', $instance->selectsubgroupindex);

        } else {
            $mform->addElement('select', 'selectsubgroup', get_string('subgroup', 'enrol_ilios'), $subgroupoptions);
            $mform->addHelpButton('selectsubgroup', 'subgroup', 'enrol_ilios');
            $mform->disabledIf('selectsubgroup', 'selectlearnergroup', 'eq', '');
            $mform->registerNoSubmitButton('updatesubgroupoptions');
            // $mform->addElement('submit', 'updatesubgroupoptions', get_string('subgroupoptionsupdate', 'enrol_ilios'));
            $mform->addElement('submit', 'updatesubgroupoptions', 'Update subgroup option');
        }

        // Role assignment
        $mform->addElement('header','roleassignments', get_string('roleassignments', 'role'));

        $roles = get_assignable_roles($coursecontext);
        $roles[0] = get_string('none');
        $roles = array_reverse($roles, true); // Descending default sortorder.
        if ($instance->id) {
            if (!isset($roles[$instance->roleid])) {
                if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                    $roles = role_fix_names($roles, $coursecontext, ROLENAME_ALIAS, true);
                    $roles[$instance->roleid] = role_get_name($role, $coursecontext);
                } else {
                    $roles[$instance->roleid] = get_string('error');
                }
            }
        }

        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_ilios'), $roles);
        $mform->setDefault('roleid', $enrol->get_config('roleid'));

        // Group assignment
        // $mform->addElement('header','groupassignment', 'Group assignment');

        $groups = array(0 => get_string('none'));
        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context'=>$coursecontext));
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
        $prog_el =& $mform->getElement('selectschool');
    }

    function definition_after_data() {
        global $DB;
        $mform = $this->_form;

        $prog_el = $mform->getElement('selectschool');
        if ($prog_el->isFrozen()) {
            return;
        }

        $enrol = enrol_get_plugin('ilios');
        $http = $enrol->get_http_client();

        $selectvalues = $mform->getElementValue('selectschool');
        if (is_array($selectvalues)) {
            if (strstr($selectvalues[0],':'))
                list($schoolid, $schooltitle) = explode(':', $selectvalues[0], 2);
            else
                list($schoolid, $schooltitle) = array($selectvalues[0],'','');
        } else {
            $schoolid = '';
            $schooltitle = '';
        }

        $selectvalues = $mform->getElementValue('selectprogram');
        if (is_array($selectvalues)) {
            if (strstr($selectvalues[0],':'))
                list($programid, $programshorttitle, $programtitle) = explode(':', $selectvalues[0], 3);
            else
                list($programid, $programshorttitle, $programtitle)
                    = array( $selectvalues[0], '', '');
        } else {
                list($programid, $programshorttitle, $programtitle)
                    = array( '', '', '');
        }

        $selectvalues = $mform->getElementValue('selectcohort');
        if (is_array($selectvalues)) {
            if (strstr($selectvalues[0],':'))
                list($cohortid, $cohorttitle) = explode(':', $selectvalues[0], 2);
            else
                list($cohortid, $cohorttitle) = array($selectvalues[0],'','');
        } else {
            $cohortid = '';
            $cohorttitle = '';
        }

        $selectvalues = $mform->getElementValue('selectlearnergroup');
        if (is_array($selectvalues)) {
            if (strstr($selectvalues[0],':'))
                list($learnergroupid, $learnergrouptitle) = explode(':', $selectvalues[0], 2);
            else
                list($learnergroupid, $learnergrouptitle) = array($selectvalues[0],'','');
        } else {
            $learnergroupid = '';
            $learnergrouptitle = '';
        }

        $schools = $http->get('schools', '', array('title' => "ASC"));

        $prog_el =& $mform->getElement('selectschool');
        if ($schools === null) { // no connection to the server
            // @TODO: get from cache if possible
            $schooloptions = array('' => get_string('error'));
            $prog_el->load($schooloptions);
        } else {
            foreach ($schools as $school) {
                $prog_el->addOption( $school->title, "$school->id:$school->title" );
            }
        }

        if (!empty($schoolid)) {
            $sid = $schoolid;
            $prog_el =& $mform->getElement('selectprogram');
            $programoptions = array();
            $programs = array();
            $programs = $http->get(
                'programs',
                array('school' => $sid),
                array('title' => "ASC")
            );

            if (!empty($programs)) {
                foreach ($programs as $program) {
                    $programoptions["$program->id:$program->shortTitle:$program->title"] = $program->title;
                }
                $prog_el->load($programoptions);
            }
        }

        if (!empty($programid)) {
            $pid = $programid;
            $prog_el =& $mform->getElement('selectcohort');
            $cohortoptions = array();

            $programyears = $http->get('programYears',
                                       array("program" => $pid),
                                       array("startYear" => "ASC"));
            $programyeararray = array();
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
                $prog_el->load($cohortoptions);
            }
        }

        if (!empty($cohortid)) {
            $cid = $cohortid;
            $prog_el =& $mform->getElement('selectlearnergroup');
            $learnergroupoptions = array();

            $learnergroups = $http->get('learnerGroups',
                                        array('cohort' => $cid, 'parent' => 'null'),
                                        array('title' => "ASC"));
            if (!empty($learnergroups)) {
                foreach ($learnergroups as $group) {
                    $learnergroupoptions["$group->id:$group->title"] = $group->title.
                                                     ' ('. count($group->children) .')'.
                                                     ' ('. count($group->users) .')';
                }
                $prog_el->load($learnergroupoptions);
            }
        }

        if (!empty($learnergroupid)) {
            $gid = $learnergroupid;
            $prog_el =& $mform->getElement('selectsubgroup');
            $subgroupoptions = array();

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
                            if (!empty($subgrp->children)) {
                                $processchildren($subgrp);
                            }
                        }
                    };
                    $processchildren($subgroup);
                }
            }
            $prog_el->load($subgroupoptions);
        }
    }

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Make sure a learner group is selected if customint2 = 1 (instructor)
        if (!empty($data['selectusertype'])) {
            if (empty($data['selectlearnergroup'])) {
                $errors['selectlearnergroup'] = get_string('requiredforinstructor', 'enrol_ilios');
            }
        }

        // Check for existing role
        $selectgrouptype = 'cohort';
        list($selectgroupid,$selecttitle) = explode(':',$data['selectcohort'], 2);
        if (!empty($data['selectlearnergroup'])){
            $selectgrouptype = 'learnerGroup';
            list($selectgroupid,$selecttitle) = explode(':',$data['selectlearnergroup'], 2);
            if (!empty($data['selectsubgroup'])) {
                list($selectgroupid,$selecttitle) = explode(':',$data['selectsubgroup'], 2);
            }
        }

        $params = array('roleid'=>$data['roleid'], 'customchar1'=>$selectgrouptype,'customint1'=>$selectgroupid,'customint2'=>$data['selectusertype'],'courseid'=>$data['courseid'], 'id'=>$data['id']);
        // customint2 could be NULL or 0 on the database
        if (empty($data['selectusertype']) && $DB->record_exists_select('enrol', "roleid = :roleid AND customchar1 = :customchar1 AND customint1 = :customint1 AND customint2 IS NULL AND courseid = :courseid AND enrol = 'ilios' AND id <> :id", $params)) {
                $errors['roleid'] = get_string('instanceexists', 'enrol_ilios');
        } else {
            if ($DB->record_exists_select('enrol', "roleid = :roleid AND customchar1 = :customchar1 AND customint1 = :customint1 AND customint2 = :customint2 AND courseid = :courseid AND enrol = 'ilios' AND id <> :id", $params)) {
                $errors['roleid'] = get_string('instanceexists', 'enrol_ilios');
            }
        }

        return $errors;
    }
}
