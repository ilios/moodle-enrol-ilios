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
 * @copyright  2015 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

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
            // get_string('defaultrole', 'role'), '', $student->id, $options));
            'Default student role', '', $student->id, $options));

        $teacher = get_archetype_roles('teacher');
        $teacher = reset($teacher);
        $settings->add(new admin_setting_configselect('enrol_ilios/roleinstructorid',
            'Default instructor role', '', $teacher->id, $options));

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ilios/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));
    }
}
