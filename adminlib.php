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
 * UCSF Student Information System enrolment plugin settings and presets.
 *
 * @package    enrol_ilios
 * @copyright  2017 The Regents of the University of California
 * @author     Carson Tam
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/adminlib.php");

/**
 * Setting class that create tabs
 *
 */
class ilios_admin_setting_tabtree extends admin_setting {
    /** @var array Array of tabs */
    public $tabs;
    /**
     * not a setting, just text and links
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $heading heading
     * @param string $information text in box
     * @param string $currenttab selected tab
     * @param array  $tabs an array of tabobjects
     */
    public function __construct($name, $heading, $information, $currenttab, $tabs) {
        $this->nosave = true;
        $this->tabs = $tabs;

        parent::__construct($name, $heading, $information, $currenttab);
    }

    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Never write settings
     * @return string Always returns an empty string
     */
    public function write_setting($data) {
    // do not write any setting
        return '';
    }

    /**
     * Returns an HTML string
     * @return string Returns an HTML string
     */
    public function output_html($data, $query='') {
        global $OUTPUT;

        $return = $OUTPUT->tabtree($this->tabs, $this->get_defaultsetting());

        return $return;
    }
}
