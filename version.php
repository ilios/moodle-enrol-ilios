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
 * Ilios enrolment plugin version file.
 *
 * @package    enrol_ilios
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2017 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024061001;       // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2023100400;       // Requires this Moodle version.
$plugin->component = 'enrol_ilios';     // Full name of the plugin (used for diagnostics).
$plugin->release = 'v4.3';
$plugin->supported = [403, 403];
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = [
    'local_iliosapiclient' => 2024061000,
];
