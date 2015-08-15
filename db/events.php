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
 * Ilios enrolment plugin event handler definition.
 *
 * @package enrol_ilios
 * @category event
 * @copyright 2015 The Regents of the University of California
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(

    array(
        'eventname' => '\core\event\ilios_member_added',
        'callback' => 'enrol_ilios_handler::member_added',
        'includefile' => '/enrol/ilios/locallib.php'
    ),

    array(
        'eventname' => '\core\event\ilios_member_removed',
        'callback' => 'enrol_ilios_handler::member_removed',
        'includefile' => '/enrol/ilios/locallib.php'
    ),

    array(
        'eventname' => '\core\event\ilios_deleted',
        'callback' => 'enrol_ilios_handler::deleted',
        'includefile' => '/enrol/ilios/locallib.php'
    ),
);
