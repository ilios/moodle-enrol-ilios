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
 * Test coverage for the Ilios API client.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios;

use advanced_testcase;
use core\di;
use core\http_client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use moodle_exception;
use enrol_ilios\tests\helper;

/**
 * Tests the Ilios API client.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_ilios\ilios
 */
final class ilios_test extends advanced_testcase {

    /**
     * Tests the happy path on get_schools().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_schools(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'schools' => [
                    ['id' => 1, 'title' => 'Medicine'],
                    ['id' => 2, 'title' => 'Pharmacy'],
                ],
            ])),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $schools = $ilios->get_schools();
        $this->assertCount(2, $schools);
        $this->assertEquals(1, $schools[0]->id);
        $this->assertEquals('Medicine', $schools[0]->title);
        $this->assertEquals(2, $schools[1]->id);
        $this->assertEquals('Pharmacy', $schools[1]->title);
    }

    /**
     * Tests the happy path on get_enabled_users_in_school().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_enabled_users_in_school(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'users' => [
                    ['id' => 1, 'campusId' => 'xx00001'],
                    ['id' => 2, 'campusId' => 'xx00002'],
                ],
            ])),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $users = $ilios->get_enabled_users_in_school(123);
        $this->assertCount(2, $users);
        $this->assertEquals(1, $users[0]->id);
        $this->assertEquals('xx00001', $users[0]->campusId);
        $this->assertEquals(2, $users[1]->id);
        $this->assertEquals('xx00002', $users[1]->campusId);
    }

    /**
     * Tests the happy path on get().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'schools' => [
                    ['id' => 1, 'title' => 'Medicine'],
                    ['id' => 2, 'title' => 'Pharmacy'],
                ],
            ])),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $data = $ilios->get('schools');
        $this->assertCount(2, $data->schools);
        $this->assertEquals(1, $data->schools[0]->id);
        $this->assertEquals('Medicine', $data->schools[0]->title);
        $this->assertEquals(2, $data->schools[1]->id);
        $this->assertEquals('Pharmacy', $data->schools[1]->title);
    }

    /**
     * Tests that get() fails if the response cannot be JSON-decoded.
     *
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_on_garbled_response(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], 'g00bleG0bble'),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Failed to decode response.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the response is empty.
     *
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_on_empty_response(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], ''),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Empty response.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the response contains errors.
     *
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_on_error_response(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['errors' => ['something went wrong']])),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('The API responded with the following error: something went wrong.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the server response with a non 200 response, for example a 500 error.
     *
     * @return void
     * @throws moodle_exception
     */
    public function test_get_fails_on_server_side_error(): void {
        $this->resetAfterTest();
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(500),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $this->expectException(GuzzleException::class);
        // phpcs:disable moodle.Strings.ForbiddenStrings.Found
        $this->expectExceptionMessage(
            'Server error: `GET http://ilios.demo/api/v3/schools` resulted in a `500 Internal Server Error` response'
        );
        // phpcs:enable
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the given access token is expired.
     *
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_with_expired_token(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_invalid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('API token is expired.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the given access token is empty.
     *
     * @dataProvider empty_token_provider
     * @param string $accesstoken The API access token.
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_with_empty_token(string $accesstoken): void {
        $this->resetAfterTest();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('API token is empty.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the given access token cannot be JSON-decoded.
     *
     * @dataProvider corrupted_token_provider
     * @param string $accesstoken The API access token.
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_with_corrupted_token(string $accesstoken): void {
        $this->resetAfterTest();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Failed to decode API token.');
        $ilios->get('schools');
    }

    /**
     * Tests that get() fails if the given access token has the wrong number of segments.
     *
     * @dataProvider invalid_token_provider
     * @param string $accesstoken The API access token.
     * @return void
     * @throws GuzzleException
     */
    public function test_get_fails_with_invalid_token(string $accesstoken): void {
        $this->resetAfterTest();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $ilios = di::get(ilios::class);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('API token has an incorrect number of segments.');
        $ilios->get('schools');
    }

    /**
     * Tests that get_access_token_payload() fails if the given access token has the wrong number of segments.
     *
     * @dataProvider invalid_token_provider
     * @param string $accesstoken The API access token.
     * @return void
     */
    public function test_get_access_token_payload_fails_with_invalid_token(string $accesstoken): void {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('API token has an incorrect number of segments.');
        ilios::get_access_token_payload($accesstoken);
    }

    /**
     * Tests that get_access_token_payload() fails if the given access token cannot be JSON-decoded.
     *
     * @dataProvider corrupted_token_provider
     * @param string $accesstoken The API access token.
     * @return void
     */
    public function test_get_access_token_payload_fails_with_corrupted_token(string $accesstoken): void {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Failed to decode API token.');
        ilios::get_access_token_payload($accesstoken);
    }

    /**
     * Returns empty access tokens.
     *
     * @return array[]
     */
    public static function empty_token_provider(): array {
        return [
            [''],
            ['   '],
        ];
    }

    /**
     * Returns "corrupted" access tokens.
     *
     * @return array[]
     */
    public static function corrupted_token_provider(): array {
        return [
            ['AAAAA.BBBBB.CCCCCC'], // Has the right number of segments, but bunk payload.
        ];
    }

    /**
     * Returns access tokens with invalid numbers of segments.
     *
     * @return array[]
     */
    public static function invalid_token_provider(): array {
        return [
            ['AAAA'], // Not enough segments.
            ['AAAA.BBBBB'], // Still not enough.
            ['AAAA.BBBBB.CCCCC.DDDDD'], // Too many segments.
        ];
    }
}

