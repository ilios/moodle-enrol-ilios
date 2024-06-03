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
 * Meta link enrolment plugin uninstallation.
 *
 * @package    enrol_ilios
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Callback to run during plugin uninstallation.
 * Deletes all Ilios enrolments and removes any user role assignments for this plugin.
 *
 * @return bool Always TRUE.
 * @throws coding_exception
 * @throws dml_exception
 */
function xmldb_enrol_ilios_uninstall() {
    global $CFG, $DB;

    $ilios = enrol_get_plugin('ilios');
    $rs = $DB->get_recordset('enrol', ['enrol' => 'ilios']);
    foreach ($rs as $instance) {
        $ilios->delete_instance($instance);
    }
    $rs->close();

    role_unassign_all(['component' => 'enrol_ilios']);

    return true;
}
