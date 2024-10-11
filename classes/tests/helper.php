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
 * Provides utility methods for testing.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios\tests;

use DateTime;
use Firebase\JWT\JWT;

/**
 * A class providing utility methods for testing.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Generates an un-expired JWT, to be used as access token.
     * This token will pass client-side token validation.
     *
     * @return string
     */
    public static function create_valid_ilios_api_access_token(): string {
        $key = 'doesnotmatterhere';
        $payload = ['exp' => (new DateTime('10 days'))->getTimestamp()];
        return JWT::encode($payload, $key, 'HS256');
    }

    /**
     * Generates an expired - and therefore invalid - JWT, to be used as access token.
     * This token will fail client-side token validation.
     *
     * @return string
     */
    public static function create_invalid_ilios_api_access_token(): string {
        $key = 'doesnotmatterhere';
        $payload = ['exp' => (new DateTime('-2 days'))->getTimestamp()];
        return JWT::encode($payload, $key, 'HS256');
    }
}
