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
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use moodle_exception;
use enrol_ilios\tests\helper;
use Psr\Http\Message\RequestInterface;

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
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'schools' => [
                    ['id' => 1, 'title' => 'Medicine', 'programs' => ['2', '4']],
                    ['id' => 2, 'title' => 'Pharmacy', 'programs' => ['3', '5']],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $schools = $ilios->get_schools();

        $this->assertEquals('/api/v3/schools', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $schools);
        $this->assertEquals(1, $schools[0]->id);
        $this->assertEquals('Medicine', $schools[0]->title);
        $this->assertEquals(['2', '4'], $schools[0]->programs);
        $this->assertEquals(2, $schools[1]->id);
        $this->assertEquals('Pharmacy', $schools[1]->title);
        $this->assertEquals(['3', '5'], $schools[1]->programs);
    }

    /**
     * Tests the happy path on get_cohorts().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_cohorts(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'cohorts' => [
                    [
                        'id' => 1,
                        'title' => 'Class of 2023',
                        'programYear' => 1,
                        'courses' => ['3'],
                        'users' => ['1', '2'],
                        'learnerGroups' => ['5', '8'],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Class of 2024',
                        'programYear' => 3,
                        'courses' => [],
                        'users' => [],
                        'learnerGroups' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $cohorts = $ilios->get_cohorts();

        $this->assertEquals('/api/v3/cohorts', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $cohorts);
        $this->assertEquals(1, $cohorts[0]->id);
        $this->assertEquals('Class of 2023', $cohorts[0]->title);
        $this->assertEquals(1, $cohorts[0]->programYear);
        $this->assertEquals(['3'], $cohorts[0]->courses);
        $this->assertEquals(['1', '2'], $cohorts[0]->users);
        $this->assertEquals(['5', '8'], $cohorts[0]->learnerGroups);
        $this->assertEquals(2, $cohorts[1]->id);
        $this->assertEquals('Class of 2024', $cohorts[1]->title);
        $this->assertEquals(3, $cohorts[1]->programYear);
        $this->assertEquals([], $cohorts[1]->courses);
        $this->assertEquals([], $cohorts[1]->users);
        $this->assertEquals([], $cohorts[1]->learnerGroups);
    }

    /**
     * Tests the happy path on get_programs().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_programs(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'programs' => [
                    [
                        'id' => 1,
                        'title' => 'Doctor of Medicine - MD',
                        'shortTitle' => 'MD',
                        'school' => 1,
                        'programYears' => ['1', '2'],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Doctor of Medicine - Bridges',
                        'shortTitle' => 'Bridges',
                        'school' => 2,
                        'programYears' => ['3'],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $programs = $ilios->get_programs();

        $this->assertEquals('/api/v3/programs', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $programs);
        $this->assertEquals(1, $programs[0]->id);
        $this->assertEquals('Doctor of Medicine - MD', $programs[0]->title);
        $this->assertEquals('MD', $programs[0]->shortTitle);
        $this->assertEquals(1, $programs[0]->school);
        $this->assertEquals(['1', '2'], $programs[0]->programYears);
        $this->assertEquals(2, $programs[1]->id);
        $this->assertEquals('Doctor of Medicine - Bridges', $programs[1]->title);
        $this->assertEquals('Bridges', $programs[1]->shortTitle);
        $this->assertEquals(2, $programs[1]->school);
        $this->assertEquals(['3'], $programs[1]->programYears);
    }

    /**
     * Tests the happy path on get_program_years().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_program_years(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'programYears' => [
                    [
                        'id' => 1,
                        'startYear' => 2023,
                        'program' => 1,
                        'cohort' => 2,
                    ],
                    [
                        'id' => 2,
                        'startYear' => 2024,
                        'program' => 2,
                        'cohort' => 3,
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $programs = $ilios->get_program_years();

        $this->assertEquals('/api/v3/programyears', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $programs);
        $this->assertEquals(1, $programs[0]->id);
        $this->assertEquals(2023, $programs[0]->startYear);
        $this->assertEquals(1, $programs[0]->program);
        $this->assertEquals(2, $programs[0]->cohort);
        $this->assertEquals(2, $programs[1]->id);
        $this->assertEquals(2024, $programs[1]->startYear);
        $this->assertEquals(2, $programs[1]->program);
        $this->assertEquals(3, $programs[1]->cohort);
    }

    /**
     * Tests the happy path on get_learner_groups().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_learner_groups(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'learnerGroups' => [
                    [
                        'id' => 1,
                        'title' => 'Alpha',
                        'cohort' => 1,
                        'parent' => null,
                        'children' => ['2'],
                        'ilmSessions' => ['1', '2'],
                        'offerings' => ['5', '6'],
                        'instructorGroups' => ['3', '4', '5'],
                        'instructors' => ['7'],
                        'users' => ['4', '12'],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Beta',
                        'cohort' => 2,
                        'parent' => 1,
                        'children' => [],
                        'ilmSessions' => [],
                        'offerings' => [],
                        'instructorGroups' => [],
                        'instructors' => [],
                        'users' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $learnergroups = $ilios->get_learner_groups();

        $this->assertEquals('/api/v3/learnergroups', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $learnergroups);
        $this->assertEquals(1, $learnergroups[0]->id);
        $this->assertEquals('Alpha', $learnergroups[0]->title);
        $this->assertEquals(1, $learnergroups[0]->cohort);
        $this->assertNull($learnergroups[0]->parent);
        $this->assertEquals(['2'], $learnergroups[0]->children);
        $this->assertEquals(['1', '2'], $learnergroups[0]->ilmSessions);
        $this->assertEquals(['5', '6'], $learnergroups[0]->offerings);
        $this->assertEquals(['3', '4', '5'], $learnergroups[0]->instructorGroups);
        $this->assertEquals(['7'], $learnergroups[0]->instructors);
        $this->assertEquals(['4', '12'], $learnergroups[0]->users);
        $this->assertEquals(2, $learnergroups[1]->id);
        $this->assertEquals('Beta', $learnergroups[1]->title);
        $this->assertEquals(2, $learnergroups[1]->cohort);
        $this->assertEquals(1, $learnergroups[1]->parent);
        $this->assertEquals([], $learnergroups[1]->children);
        $this->assertEquals([], $learnergroups[1]->ilmSessions);
        $this->assertEquals([], $learnergroups[1]->offerings);
        $this->assertEquals([], $learnergroups[1]->instructorGroups);
        $this->assertEquals([], $learnergroups[1]->instructors);
        $this->assertEquals([], $learnergroups[1]->users);
    }

    /**
     * Tests the happy path on get_instructor_groups().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_instructor_groups(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'instructorGroups' => [
                    [
                        'id' => 1,
                        'title' => 'Anatomy Lab Instructors',
                        'school' => 1,
                        'learnerGroups' => ['8', '9'],
                        'ilmSessions' => ['1', '2'],
                        'offerings' => ['5', '6'],
                        'users' => ['4', '12'],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Clinical Pharmacy Instructors',
                        'school' => 2,
                        'learnerGroups' => [],
                        'ilmSessions' => [],
                        'offerings' => [],
                        'users' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $instructorgroups = $ilios->get_instructor_groups();

        $this->assertEquals('/api/v3/instructorgroups', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $instructorgroups);
        $this->assertEquals(1, $instructorgroups[0]->id);
        $this->assertEquals('Anatomy Lab Instructors', $instructorgroups[0]->title);
        $this->assertEquals(1, $instructorgroups[0]->school);
        $this->assertEquals(['8', '9'], $instructorgroups[0]->learnerGroups);
        $this->assertEquals(['1', '2'], $instructorgroups[0]->ilmSessions);
        $this->assertEquals(['5', '6'], $instructorgroups[0]->offerings);
        $this->assertEquals(['4', '12'], $instructorgroups[0]->users);
        $this->assertEquals(2, $instructorgroups[1]->id);
        $this->assertEquals('Clinical Pharmacy Instructors', $instructorgroups[1]->title);
        $this->assertEquals(2, $instructorgroups[1]->school);
        $this->assertEquals([], $instructorgroups[1]->learnerGroups);
        $this->assertEquals([], $instructorgroups[1]->ilmSessions);
        $this->assertEquals([], $instructorgroups[1]->offerings);
        $this->assertEquals([], $instructorgroups[1]->users);
    }

    /**
     * Tests the happy path on get_users().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_offerings(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'offerings' => [
                    [
                        'id' => 1,
                        'learnerGroups' => ['1', '2'],
                        'instructorGroups' => ['2', '4'],
                        'learners' => ['8', '9'],
                        'instructors' => ['5', '6'],
                    ],
                    [
                        'id' => 2,
                        'learnerGroups' => [],
                        'instructorGroups' => [],
                        'learners' => [],
                        'instructors' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $offerings = $ilios->get_offerings();

        $this->assertEquals('/api/v3/offerings', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $offerings);
        $this->assertEquals(1, $offerings[0]->id);
        $this->assertEquals(['1', '2'], $offerings[0]->learnerGroups);
        $this->assertEquals(['2', '4'], $offerings[0]->instructorGroups);
        $this->assertEquals(['8', '9'], $offerings[0]->learners);
        $this->assertEquals(['5', '6'], $offerings[0]->instructors);
        $this->assertEquals(2, $offerings[1]->id);
        $this->assertEquals([], $offerings[1]->learnerGroups);
        $this->assertEquals([], $offerings[1]->instructorGroups);
        $this->assertEquals([], $offerings[1]->learners);
        $this->assertEquals([], $offerings[1]->instructors);
    }

    /**
     * Tests the happy path on get_users().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_ilms(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'ilmSessions' => [
                    [
                        'id' => 1,
                        'learnerGroups' => ['1', '2'],
                        'instructorGroups' => ['2', '4'],
                        'learners' => ['8', '9'],
                        'instructors' => ['5', '6'],
                    ],
                    [
                        'id' => 2,
                        'learnerGroups' => [],
                        'instructorGroups' => [],
                        'learners' => [],
                        'instructors' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $ilms = $ilios->get_ilms();

        $this->assertEquals('/api/v3/ilmsessions', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $ilms);
        $this->assertEquals(1, $ilms[0]->id);
        $this->assertEquals(['1', '2'], $ilms[0]->learnerGroups);
        $this->assertEquals(['2', '4'], $ilms[0]->instructorGroups);
        $this->assertEquals(['8', '9'], $ilms[0]->learners);
        $this->assertEquals(['5', '6'], $ilms[0]->instructors);
        $this->assertEquals(2, $ilms[1]->id);
        $this->assertEquals([], $ilms[1]->learnerGroups);
        $this->assertEquals([], $ilms[1]->instructorGroups);
        $this->assertEquals([], $ilms[1]->learners);
        $this->assertEquals([], $ilms[1]->instructors);
    }

    /**
     * Tests the happy path on get_users().
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_users(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'users' => [
                    [
                        'id' => 1,
                        'enabled' => true,
                        'campusId' => 'xx1000001',
                    ],
                    [
                        'id' => 2,
                        'enabled' => false,
                        'campusId' => 'xx1000002',
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $users = $ilios->get_users();

        $this->assertEquals('/api/v3/users', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $users);
        $this->assertEquals(1, $users[0]->id);
        $this->assertTrue($users[0]->enabled);
        $this->assertEquals('xx1000001', $users[0]->campusId);
        $this->assertEquals(2, $users[1]->id);
        $this->assertFalse($users[1]->enabled);
        $this->assertEquals('xx1000002', $users[1]->campusId);
    }

    /**
     * Tests retrieving a school from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_school(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'schools' => [
                    ['id' => 1, 'title' => 'Medicine', 'programs' => ['2', '4']],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $school = $ilios->get_school(1);

        $this->assertEquals('/api/v3/schools/1', $container[0]['request']->getUri()->getPath());

        $this->assertEquals(1, $school->id);
        $this->assertEquals('Medicine', $school->title);
        $this->assertEquals(['2', '4'], $school->programs);
    }

    /**
     * Tests retrieving a school that's missing from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_school_not_found(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $school = $ilios->get_school(1);
        $this->assertNull($school);
    }

    /**
     * Tests retrieving a cohort from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_cohort(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'cohorts' => [
                    [
                        'id' => 1,
                        'title' => 'Class of 2023',
                        'programYear' => 1,
                        'courses' => ['3'],
                        'users' => ['1', '2'],
                        'learnerGroups' => ['5', '8'],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $cohort = $ilios->get_cohort(1);

        $this->assertEquals('/api/v3/cohorts/1', $container[0]['request']->getUri()->getPath());

        $this->assertEquals(1, $cohort->id);
        $this->assertEquals('Class of 2023', $cohort->title);
        $this->assertEquals(1, $cohort->programYear);
        $this->assertEquals(['3'], $cohort->courses);
        $this->assertEquals(['1', '2'], $cohort->users);
        $this->assertEquals(['5', '8'], $cohort->learnerGroups);
    }

    /**
     * Tests retrieving a cohort that's missing from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_cohort_not_found(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $cohort = $ilios->get_cohort(1);
        $this->assertNull($cohort);
    }

    /**
     * Tests retrieving a program from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_program(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'programs' => [
                    [
                        'id' => 1,
                        'title' => 'Doctor of Medicine - MD',
                        'shortTitle' => 'MD',
                        'school' => 1,
                        'programYears' => ['1', '2'],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $program = $ilios->get_program(1);

        $this->assertEquals('/api/v3/programs/1', $container[0]['request']->getUri()->getPath());

        $this->assertEquals(1, $program->id);
        $this->assertEquals('Doctor of Medicine - MD', $program->title);
        $this->assertEquals('MD', $program->shortTitle);
        $this->assertEquals(1, $program->school);
        $this->assertEquals(['1', '2'], $program->programYears);
    }

    /**
     * Tests retrieving a program that's missing from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_program_not_found(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $program = $ilios->get_program(1);
        $this->assertNull($program);
    }

    /**
     * Tests retrieving a learner-group from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_learner_group(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'learnerGroups' => [
                    [
                        'id' => 1,
                        'title' => 'Alpha',
                        'cohort' => 1,
                        'parent' => null,
                        'children' => ['2'],
                        'ilmSessions' => ['1', '2'],
                        'offerings' => ['5', '6'],
                        'instructorGroups' => ['3', '4', '5'],
                        'instructors' => ['7'],
                        'users' => ['4', '12'],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Beta',
                        'cohort' => 2,
                        'parent' => 1,
                        'children' => [],
                        'ilmSessions' => [],
                        'offerings' => [],
                        'instructorGroups' => [],
                        'instructors' => [],
                        'users' => [],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $learnergroup = $ilios->get_learner_group(1);

        $this->assertEquals('/api/v3/learnergroups/1', $container[0]['request']->getUri()->getPath());

        $this->assertEquals(1, $learnergroup->id);
        $this->assertEquals('Alpha', $learnergroup->title);
        $this->assertEquals(1, $learnergroup->cohort);
        $this->assertNull($learnergroup->parent);
        $this->assertEquals(['2'], $learnergroup->children);
        $this->assertEquals(['1', '2'], $learnergroup->ilmSessions);
        $this->assertEquals(['5', '6'], $learnergroup->offerings);
        $this->assertEquals(['3', '4', '5'], $learnergroup->instructorGroups);
        $this->assertEquals(['7'], $learnergroup->instructors);
        $this->assertEquals(['4', '12'], $learnergroup->users);
    }

    /**
     * Tests retrieving a learner-group that's missing from Ilios.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_learner_group_not_found(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $learnergroup = $ilios->get_learner_group(1);
        $this->assertNull($learnergroup);
    }

    /**
     * Tests retrieving instructors for a given learner-group and its subgroups.
     *
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_instructor_ids_from_learner_group(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        // The user ids in the 900 range are users we don't want in the output.
        // All other user ids, 1-9 should be in the output of this function.
        // Some of these are assigned instructors in various ways, so we can verify that de-duping works.
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'learnerGroups' => [
                    [
                        'id' => 1,
                        'title' => 'Alpha',
                        'cohort' => 1,
                        'parent' => null,
                        'children' => ['2', '3'],
                        'ilmSessions' => ['1', '2'],
                        'offerings' => ['1', '2'],
                        'instructors' => ['900'],
                        'instructorGroups' => ['900'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'offerings' => [
                    [
                        'id' => 1,
                        'instructors' => [],
                        'instructorGroups' => ['1'],
                        'learners' => ['901', '902'],
                        'learnerGroups' => ['1'],
                    ],
                    [
                        'id' => 2,
                        'instructors' => ['1'],
                        'instructorGroups' => [],
                        'learners' => ['903'],
                        'learnerGroups' => ['1'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'ilmSessions' => [
                    [
                        'id' => 1,
                        'instructors' => [],
                        'instructorGroups' => ['1'],
                        'learners' => ['901', '902'],
                        'learnerGroups' => ['1'],
                    ],
                    [
                        'id' => 2,
                        'instructors' => ['1'],
                        'instructorGroups' => [],
                        'learners' => ['903'],
                        'learnerGroups' => ['1'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'learnerGroups' => [
                    [
                        'id' => 2,
                        'title' => 'Beta',
                        'cohort' => 1,
                        'parent' => 1,
                        'children' => [],
                        'ilmSessions' => [],
                        'offerings' => ['3'],
                        'instructors' => ['2'],
                        'instructorGroups' => ['2'],

                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'offerings' => [
                    [
                        'id' => 3,
                        'instructors' => [],
                        'instructorGroups' => [],
                        'learners' => ['904'],
                        'learnerGroups' => ['2'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'instructorGroups' => [
                    [
                        'id' => 2,
                        'title' => 'Zwei',
                        'school' => 1,
                        'learnerGroups' => ['2'],
                        'ilmSessions' => [],
                        'offerings' => [],
                        'users' => ['6', '7'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'learnerGroups' => [
                    [
                        'id' => 3,
                        'title' => 'Gamma',
                        'cohort' => 1,
                        'parent' => 1,
                        'children' => [],
                        'ilmSessions' => ['3'],
                        'offerings' => [],
                        'instructors' => ['3'],
                        'instructorGroups' => ['3'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'ilmSessions' => [
                    [
                        'id' => 3,
                        'instructors' => [],
                        'instructorGroups' => [],
                        'learners' => ['905'],
                        'learnerGroups' => ['2'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'instructorGroups' => [
                    [
                        'id' => 3,
                        'title' => 'Drei',
                        'school' => 1,
                        'learnerGroups' => [],
                        'ilmSessions' => [],
                        'offerings' => ['1'],
                        'users' => ['8', '9'],
                    ],
                ],
            ])),
            new Response(200, [], json_encode([
                'instructorGroups' => [
                    [
                        'id' => 1,
                        'title' => 'Eins',
                        'school' => 1,
                        'learnerGroups' => [],
                        'ilmSessions' => ['1'],
                        'offerings' => ['1'],
                        'users' => ['4', '5'],
                    ],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $ids = $ilios->get_instructor_ids_from_learner_group(1);

        $this->assertEquals('/api/v3/learnergroups/1', $container[0]['request']->getUri()->getPath());
        $this->assertEquals('/api/v3/offerings', $container[1]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=1&filters[id][]=2', urldecode($container[1]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/ilmsessions', $container[2]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=1&filters[id][]=2', urldecode($container[2]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/learnergroups/2', $container[3]['request']->getUri()->getPath());
        $this->assertEquals('/api/v3/offerings', $container[4]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=3', urldecode($container[4]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/instructorgroups', $container[5]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=2', urldecode($container[5]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/learnergroups/3', $container[6]['request']->getUri()->getPath());
        $this->assertEquals('/api/v3/ilmsessions', $container[7]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=3', urldecode($container[7]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/instructorgroups', $container[8]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=3', urldecode($container[8]['request']->getUri()->getQuery()));
        $this->assertEquals('/api/v3/instructorgroups', $container[9]['request']->getUri()->getPath());
        $this->assertEquals('filters[id][]=1', urldecode($container[9]['request']->getUri()->getQuery()));

        $this->assertEquals(['1', '2', '3', '4', '5', '6', '7', '8', '9'], $ids);
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
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');
        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'schools' => [
                    ['id' => 1, 'title' => 'Medicine'],
                    ['id' => 2, 'title' => 'Pharmacy'],
                ],
            ])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $data = $ilios->get('schools');

        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertEquals('ilios.demo', $container[0]['request']->getUri()->getHost());
        $this->assertEquals('/api/v3/schools', $container[0]['request']->getUri()->getPath());

        $this->assertCount(2, $data->schools);
        $this->assertEquals(1, $data->schools[0]->id);
        $this->assertEquals('Medicine', $data->schools[0]->title);
        $this->assertEquals(2, $data->schools[1]->id);
        $this->assertEquals('Pharmacy', $data->schools[1]->title);
    }

    /**
     * Tests get() with filter- and sorting criteria as input.
     *
     * @dataProvider get_with_filtering_and_sorting_provider
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @param string $expectedquerystring The expected query string that the given criteria transform into.
     * @return void
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function test_get_with_filtering_and_sorting_criteria(
        array $filterby,
        array $sortby,
        string $expectedquerystring): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['geflarkniks' => [['doesnt-really' => 'matter']]])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $ilios->get('geflarkniks', $filterby, $sortby);

        $this->assertEquals($expectedquerystring, urldecode($container[0]['request']->getUri()->getQuery()));
    }

    /**
     * Data provider for test_get_with_filtering_and_sorting_criteria().
     * Returns test filter and sorting criteria and their expected transformation into a query string.
     *
     * @return array[]
     */
    public static function get_with_filtering_and_sorting_provider(): array {
        return [
            [[], [], ''],
            [['foo' => 'bar'], [], 'filters[foo]=bar'],
            [[], ['name' => 'DESC'], 'order_by[name]=DESC'],
            [
                ['id' => [1, 2], 'school' => 5],
                ['title' => 'ASC'],
                'filters[id][]=1&filters[id][]=2&filters[school]=5&order_by[title]=ASC',
            ],
        ];
    }


    /**
     * Tests retrieving a resource by its ID from the Ilios API.
     */
    public function test_get_by_id(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['geflarkniks' => [
                ['id' => 1, 'title' => 'whatever'],
            ]])),
        ]));
        $container = [];
        $history = Middleware::history($container);
        $handlerstack->push($history);
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        $ilios = di::get(ilios::class);
        $response = $ilios->get_by_id('geflarkniks', 12345);

        $this->assertEquals('/api/v3/geflarkniks/12345', $container[0]['request']->getUri()->getPath());

        $this->assertObjectHasProperty('geflarkniks', $response);
        $this->assertCount(1, $response->geflarkniks);
        $this->assertEquals('1', $response->geflarkniks[0]->id);
        $this->assertEquals('whatever', $response->geflarkniks[0]->title);
    }

    /**
     * Tests that get_by_id() raises an exception if Ilios responds with a 404/not-found.
     */
    public function test_get_by_id_fails_on_404(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404, []),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('404 Not Found');
        $this->expectExceptionCode(404);
        $ilios->get_by_id('does-not-matter-here', 12345, false);
    }

    /**
     * Tests that get_by_id() returns NULL if Ilios responds with a 404/not-found.
     */
    public function test_get_by_id_returns_null_on_404(): void {
        $this->resetAfterTest();
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');

        $handlerstack = HandlerStack::create(new MockHandler([
            new Response(404, []),
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));
        $ilios = di::get(ilios::class);
        $response = $ilios->get_by_id('does-not-matter-here', 12345);
        $this->assertNull($response);
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
