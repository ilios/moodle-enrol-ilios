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

        $enrol = $plugin;

        if (!isset($CFG->ilios_http)) {
            $CFG->ilios_http = new ilios_client($enrol->get_config('host_url'),
                                                $enrol->get_config('userid'),
                                                $enrol->get_config('secret'),
                                                $enrol->get_config('apikey'));
        }
        if ($this->httpIlios === null) {
            $this->httpIlios = $CFG->ilios_http;
        }

        $mform->addElement('header','general', get_string('pluginname', 'enrol_ilios'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_ilios'), $options);

        $http = $this->httpIlios;

        // $schools = $http->get('schools');
        // // Check $schools: may not return an object.
        // if ($schools === null) { // no connection to the server
        //     $schooloptions = array('' => get_string('error'));
        // } else {
            // $schooloptions = array('' => get_string('choosedots'));

        //     foreach ($schools as $school) {
        //         if ($school->deleted) {
        //             $iliosarray['schools'][$school->id]['title'] = $schooloptions[ $school->id ] = '*'.$school->title;
        //         } else {
        //             $iliosarray['schools'][$school->id]['title'] = $schooloptions[ $school->id ] = $school->title;
        //         }
        //     }
        // }

        $schooloptions = array('' => get_string('choosedots'));
        $programoptions = array('' => get_string('choosedots'));
        $cohortoptions = array('' => get_string('choosedots'));
        $learnergroupoptions = array('' => get_string('none'));
        $subgroupoptions = array('' => get_string('none'));

        if ($instance->id) {
            $synctype = $instance->customchar1;
            $syncid = $instance->customint1;
            $syncinfo = json_decode($instance->customtext1);

            $instance->schoolid = $syncinfo->school->id;
            $schools = $http->get('schools', array('id' => $instance->schoolid));
            $school = $schools[0];
            $schooloptions = array( "$instance->schoolid" => $school->title );

            $instance->programid = $syncinfo->program->id;
            $programs = $http->get('programs', array('id' => $instance->programid));
            $program = $programs[0];
            $programoptions = array( "$instance->programid" => $program->title );

            $instance->cohortid = $syncinfo->cohort->id;
            $cohorts = $http->get('cohorts', array('id' => $instance->cohortid));
            $cohort = $cohorts[0];
            $cohortoptions = array( "$instance->cohortid" => $cohort->title );

            $instance->learnergroupid = '';
            $instance->subgroupid = '';

            if ($synctype == 'learnerGroup') {
                $instance->learnergroupid = $syncinfo->learnerGroup->id;
                $groups = $http->get('learnerGroups', array('id' => $instance->learnergroupid));
                $group = $groups[0];
                $learnergroupoptions = array("$instance->learnergroupid" => $group->title);

                if (!empty($group->parent)) {
                    $grouptitle = $group->title;
                    $processparents = function ($child) use (&$processparents,
                                                             &$learnergroupoptions,
                                                             &$grouptitle,
                                                             &$instance,
                                                             $http) {
                        $parentgroups = $http->get('learnerGroups', array('id' => $child->parent));
                        if (is_array($parentgroups)) {
                            $parentgroup = $parentgroups[0];
                            $instance->learnergroupid = $parentgroup->id;
                            $learnergroupoptions = array( "$instance->learnergroupid" => $parentgroup->title);
                            if (!empty($parentgroup->parent)){
                                $grouptitle = $parentgroup->title . ' / '. $grouptitle;
                                $processparents($parentgroup);
                            }
                        }
                    };
                    $processparents($group);
                    $instance->subgroupid = $group->id;
                    $subgroupoptions = array("$group->id" => $grouptitle);
                }
            }
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            $mform->setConstant('selectschool', $instance->schoolid);
            $mform->hardFreeze('selectschool', $instance->schoolid);
        } else {
            $mform->addElement('select', 'selectschool', get_string('school', 'enrol_ilios'), $schooloptions);
            $mform->addRule('selectschool', get_string('required'), 'required', null, 'client');
            $mform->registerNoSubmitButton('updateschooloptions');
            $mform->addElement('submit', 'updateschooloptions', get_string('schooloptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectprogram', get_string('program', 'enrol_ilios'), $programoptions);
            // TODO: need to get this from customtext1
            $mform->setConstant('selectprogram', $instance->programid);
            $mform->hardFreeze('selectprogram', $instance->programid);

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
            // TODO: use customtext1
            $mform->setConstant('selectcohort', $instance->cohortid);
            $mform->hardFreeze('selectcohort', $instance->cohortid);

        } else {
            $mform->addElement('select', 'selectcohort', get_string('cohort', 'enrol_ilios'), $cohortoptions);
            $mform->addRule('selectcohort', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('selectcohort', 'selectprogram', 'eq', '');
            $mform->registerNoSubmitButton('updatecohortoptions');
            $mform->addElement('submit', 'updatecohortoptions', get_string('cohortoptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectlearnergroup', get_string('learnergroup', 'enrol_ilios'), $learnergroupoptions);
            $mform->setConstant('selectlearnergroup', $instance->learnergroupid);
            $mform->hardFreeze('selectlearnergroup', $instance->learnergroupid);

        } else {
            $mform->addElement('select', 'selectlearnergroup', get_string('learnergroup', 'enrol_ilios'), $learnergroupoptions);
            // $mform->addRule('selectlearnergroup', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('selectlearnergroup', 'selectcohort', 'eq', '');
            $mform->registerNoSubmitButton('updatelearnergroupoptions');
            $mform->addElement('submit', 'updatelearnergroupoptions', get_string('learnergroupoptionsupdate', 'enrol_ilios'));
        }

        if ($instance->id) {
            $mform->addElement('select', 'selectsubgroup', get_string('subgroup', 'enrol_ilios'), $subgroupoptions);
            $mform->setConstant('selectsubgroup', $instance->subgroupid);
            $mform->hardFreeze('selectsubgroup', $instance->subgroupid);

        } else {
            $mform->addElement('select', 'selectsubgroup', get_string('subgroup', 'enrol_ilios'), $subgroupoptions);
            //$mform->addRule('selectsubgroup', get_string('required'), 'required', null, 'client');
            $mform->disabledIf('selectsubgroup', 'selectlearnergroup', 'eq', '');
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
    }

    function definition_after_data() {
        global $DB;
        $mform = $this->_form;
        $enrol = enrol_get_plugin('ilios');
        $http = $this->httpIlios;

        $schoolid = $mform->getElementValue('selectschool');
        $programid = $mform->getElementValue('selectprogram');
        $cohortid = $mform->getElementValue('selectcohort');
        $learnergroupid = $mform->getElementValue('selectlearnergroup');

        $schools = $http->get('schools', '', array('title' => "ASC"));

        if ($schools === null) { // no connection to the server
            $schooloptions = array('' => get_string('error'));
        } else {
            $prog_el =& $mform->getElement('selectschool');

            foreach ($schools as $school) {
                if ($school->deleted) {
                    $schooloptions[ $school->id ] = '*'.$school->title;
                } else {
                    $schooloptions[ $school->id ] = $school->title;
                }
            }
            $prog_el->load($schooloptions);
        }

        if (is_array($schoolid) && !empty($schoolid[0])) {
            $sid = $schoolid[0];
            $prog_el =& $mform->getElement('selectprogram');
            $programoptions = array();

            $programs = $http->get('programs',
                                   array( 'owningSchool' => $sid, 'deleted' => false ),
                                   array( 'title'=>"ASC"));
            if (!empty($programs)) {
                foreach ($programs as $program) {
                    $programoptions[$program->id] = $program->title;
                }
                $prog_el->load($programoptions);
            }
        }

        if (is_array($programid) && !empty($programid[0])) {
            $pid = $programid[0];
            $prog_el =& $mform->getElement('selectcohort');
            $cohortoptions = array();

            $programyears = $http->get('programYears',
                                       array("program" => $pid, "deleted" => false),
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
                    $cohortoptions[$cohort->id] = $cohort->title
                                                .' ('.count($cohort->learnerGroups).')' ;
                }
                $prog_el->load($cohortoptions);
            }
        }

        if (is_array($cohortid) && !empty($cohortid[0])) {
            $cid = $cohortid[0];
            $prog_el =& $mform->getElement('selectlearnergroup');
            $learnergroupoptions = array();

            $learnergroups = $http->get('learnerGroups',
                                        array('cohort' => $cid, 'parent' => 'null'),
                                        array('title' => "ASC"));
            if (!empty($learnergroups)) {
                foreach ($learnergroups as $group) {
                    $learnergroupoptions[$group->id] = $group->title.
                                                     ' ('. count($group->children) .')'.
                                                     ' ('. count($group->users) .')';
                }
                $prog_el->load($learnergroupoptions);
            }
        }

        if (is_array($learnergroupid) && !empty($learnergroupid[0])) {
            $gid = $learnergroupid[0];
            $prog_el =& $mform->getElement('selectsubgroup');
            $subgroupoptions = array();

            $subgroups = $http->get('learnerGroups',
                                 array("parent" => $gid),
                                 array("title" => "ASC"));
            foreach ($subgroups as $subgroup) {
                $subgroupoptions[$subgroup->id] = $subgroup->title.
                                                ' ('. count($subgroup->children) .')'.
                                                ' ('. count($subgroup->users) .')';
                if (!empty($subgroup->children)) {
                    $processchildren = function ($parent) use (&$processchildren,&$subgroupoptions,$http) {
                        $subgrps = $http->get('learnerGroup',
                                              array( 'parent' => $parent->id),
                                              array( 'title' => "ASC"));
                        foreach ($subgrps as $subgrp) {
                            $subgroupoptions[$subgrp->id] = $parent->title.' / '.$subgrp->title.
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
            $prog_el->load($subgroupoptions);
        }
    }

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $selectgrouptype = 'cohort';
        $selectgroupid = $data['selectcohort'];
        if (!empty($data['selectlearnergroup'])){
            $selectgrouptype = 'learnerGroup';
            $selectgroupid = $data['selectlearnergroup'];
            if (!empty($data['selectsubgroup'])) {
                $selectgroupid = $data['selectsubgroup'];
            }
        }

        $params = array('roleid'=>$data['roleid'], 'customchar1'=>$selectgrouptype,'customint1'=>$selectgroupid, 'courseid'=>$data['courseid'], 'id'=>$data['id']);
        if ($DB->record_exists_select('enrol', "roleid = :roleid AND customchar1 = :customchar1 AND customint1 = :customint1 AND courseid = :courseid AND enrol = 'ilios' AND id <> :id", $params)) {
            $errors['roleid'] = get_string('instanceexists', 'enrol_ilios');
        }

        return $errors;
    }
}
