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
 * Scheduled task for processing Ilios enrolments.
 *
 * @package    enrol_ilios
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios\task;

defined('MOODLE_INTERNAL') || die;

/**
 * Simple task to run sync enrolments.
 *
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ilios_sync_task extends \core\task\scheduled_task {

    /**
     * @inheritdoc
     */
    public function get_name() {
        return get_string('iliossync', 'enrol_ilios');
    }

    /**
     * @inheritdoc
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/ilios/lib.php');

        if (!enrol_is_enabled('ilios')) {
            return;
        }

        $plugin = enrol_get_plugin('ilios');
        $result = $plugin->sync(new \text_progress_trace());
        return $result;

    }
}
