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
 * CLI sync for full ILIOS synchronisation.
 *
 * This script is meant to be called from a cronjob to sync moodle with the ILIOS
 * backend in those setups where the ILIOS backend acts as 'master' for enrolment.
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/enrol/ilios/cli/sync.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d momory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *
 * @package    enrol_ilios
 * @author     Carson Tam
 * @copyright 2015 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/clilib.php");

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

if (!enrol_is_enabled('ilios')) {
    cli_error(get_string('pluginnotenabled', 'enrol_ilios'), 2);
}

/** @var enrol_ldap_plugin $enrol */
$enrol = enrol_get_plugin('ilios');

$trace = new text_progress_trace();

// Update enrolments -- these handlers should autocreate courses if required.
$enrol->cron($trace);

exit(0);
