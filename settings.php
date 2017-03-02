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
 * Ilios enrolment plugin settings and presets.
 *
 * @package    enrol_ilios
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/adminlib.php');
// require_once($CFG->dirroot . '/enrol/locallib.php');
// require_once($CFG->dirroot . '/enrol/ilios/lib.php');
// require_once('lib.php');

$currenttab = optional_param('tabview', 'settings', PARAM_ALPHA);

if ($ADMIN->fulltree) {

    $tabs = array();
    $tabs[] = new tabobject('settings',
                            new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsilios')),
                            get_string('settings'));

    $tabs[] = new tabobject('roleassignments',
                            new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsilios', 'tabview' => 'roleassignments')),
                            'Role assignments');

    $settings->add(new ilios_admin_setting_tabtree('enrol_ilios_settings_tabtree', 'treetabvisiblename', 'treetabinformation', $currenttab, $tabs));


    if ($currenttab == "roleassignments") {
        // TODO: List category and roles assignment here.
        // $html = html_writer::start_div('category-listing');
        //$listing = coursecat::get(0)->get_children();

        // $html .= html_writer::tag('pre', var_dump($listing));

        // $html .= html_writer::end_div();

        $baseurl = '';


        $table = new html_table();
        $table->head = array("Ilios school", "Ilios user role", "", "Category", "Role", "Edit");
        $table->data = array();
        $table->id = 'manageiliosroleassignment';

        $table->data[] = new html_table_row( array(
            "School of Medicine",
            "Instructor",
            " => ",
            "School of Medicine / Bridges",
            "SOM Participant",
            '<a href="delete.php">delete</a>') );
        $table->data[] = new html_table_row( array(
            "School of Medicine",
            "Faculty",
            " => ",
            "School of Medicine / Bridges",
            "SOM Participant",
            '<a href="delete.php">delete</a>') );
        $table->data[] = new html_table_row( array(
            "School of Medicine",
            "Instructor",
            " => ",
            "School of Medicine / Faculty Development",
            "SOM Participant",
            '<a href="delete.php">delete</a>') );


        $html = html_writer::table($table);


        $settings->add(new admin_setting_heading('enrol_ilios_role_assignment_settings', '', $html));

        // Add new instance

        $settings->add(new admin_setting_heading('enrol_ilios_add_new_role_assignment', 'Add new role assignment', ''));
        require_once($CFG->libdir.'/coursecatlib.php');

        $options = array();
        $options[] = "Select a school";
        $options[] = "School of Dentistry";
        $options[] = "School of Medicine";
        $options[] = "School of Nursing";
        $options[] = "School of Pharmacy";
        $settings->add(new admin_setting_configselect('enrol_ilios/iliosschool', 'Ilios school', '', 0, $options));

        $options = array();
        $options[] = "Select a user role";
        $options[] = "Instructor";
        $options[] = "Developer";
        $settings->add(new admin_setting_configselect('enrol_ilios/iliosrole', 'Ilios user role', '', 0, $options));

        $options = array( '0' => 'Select a category' );
        $options = array_merge($options, coursecat::make_categories_list('moodle/category:manage'));
        $settings->add(new admin_setting_configselect('enrol_ilios/category', 'Select a category', '', 0, $options));

        $options = array( '0' => 'Select a role' );
        $options = array_merge( $options, get_default_enrol_roles(context_system::instance()) );
        $settings->add(new admin_setting_configselect('enrol_ilios/role', 'Select a role', '', 0, $options));


    } else {
        //--- general settings -----------------------------------------------------------------------------------
        $settings->add(new admin_setting_heading('enrol_ilios_settings', '', get_string('pluginname_desc', 'enrol_ilios')));


        //--- enrol instance defaults ----------------------------------------------------------------------------
        if (!during_initial_install()) {
            // FIX: Change host to host_url (more descriptive)
            $settings->add(new admin_setting_configtext('enrol_ilios/host_url', get_string('host_url', 'enrol_ilios'), get_string('host_url_desc', 'enrol_ilios'), 'localhost'));
            $settings->add(new admin_setting_configtext('enrol_ilios/apikey', get_string('apikey', 'enrol_ilios'), get_string('apikey_desc', 'enrol_ilios'), ''));
            $settings->add(new admin_setting_configtext('enrol_ilios/userid', get_string('userid', 'enrol_ilios'), get_string('userid_desc', 'enrol_ilios'), ''));
            $settings->add(new admin_setting_configpasswordunmask('enrol_ilios/secret', get_string('secret', 'enrol_ilios'), get_string('secret_desc', 'enrol_ilios'), ''));

            $options = get_default_enrol_roles(context_system::instance());
            $student = get_archetype_roles('student');
            $student = reset($student);
            $settings->add(new admin_setting_configselect('enrol_ilios/roleid',
                                                          get_string('defaultlearnerrole', 'enrol_ilios'),
                                                          '', $student->id, $options));

            $options = array(
                ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
            $settings->add(new admin_setting_configselect('enrol_ilios/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));


            // // Category role assignment sync
            // require_once($CFG->dirroot . '/enrol/ilios/lib.php');

            // $atoken = new stdClass;
            // $atoken->token = get_config('enrol_ilios', 'apikey');
            // $atoken->expires = get_config('enrol_ilios', 'apikeyexpires');

            // $iliosclient = new ilios_client( get_config('enrol_ilios', 'host_url'),
            //                                  get_config('enrol_ilios', 'userid'),
            //                                  get_config('enrol_ilios', 'secret'),
            //                                  $atoken );
            // echo "<pre>";
            // // var_dump($iliosclient);
            // $schools = $iliosclient->get('schools', '', array('title' => "ASC"));
            // // $schools = $iliosclient->get('schools');
            // // var_dump($schools);
            // // var_dump($atoken);
            // echo "</pre>";
        }
    }
}
