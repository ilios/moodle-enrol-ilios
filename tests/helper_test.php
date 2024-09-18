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
 * Test coverage for the test helpers.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios;

use basic_testcase;
use moodle_exception;
use enrol_ilios\tests\helper;

/**
 * Tests the test helper class.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_ilios\tests\helper
 */
final class helper_test extends basic_testcase {

    /**
     * Checks that the generator function creates a valid access token.
     * @return void
     * @throws moodle_exception
     */
    public function test_create_valid_ilios_api_access_token(): void {
        $accesstoken = helper::create_valid_ilios_api_access_token();
        $tokenpayload = ilios::get_access_token_payload($accesstoken);
        $this->assertLessThan($tokenpayload['exp'], time(), 'Token expiration date is in the future.');
    }

    /**
     * Checks that the generator function creates an invalid access token.
     * @return void
     * @throws moodle_exception
     */
    public function test_create_invalid_ilios_api_access_token(): void {
        $accesstoken = helper::create_invalid_ilios_api_access_token();
        $tokenpayload = ilios::get_access_token_payload($accesstoken);
        $this->assertLessThan(time(), $tokenpayload['exp'], 'Token expiration date is in the past.');
    }
}
