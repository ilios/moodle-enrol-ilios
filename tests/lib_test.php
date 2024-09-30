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
 * Ilios enrolment tests.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios;

use context_course;
use core\di;
use core\http_client;
use enrol_ilios\tests\helper;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use null_progress_trace;
use Psr\Http\Message\RequestInterface;

/**
 * Ilios enrolment tests.
 *
 * @category   test
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_ilios_plugin
 */
final class lib_test extends \advanced_testcase {


    /**
     * Tests the enrolment of Ilios cohort members into a Moodle course.
     */
    public function test_enrolment_from_ilios_cohort_members(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Configure the Ilios API client.
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        // Mock out the responses from the Ilios API.
        $handlerstack = HandlerStack::create(new MockHandler([
            function(RequestInterface $request) {
                $this->assertEquals('ilios.demo', $request->getUri()->getHost());
                $this->assertEquals('/api/v3/cohorts/1', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'cohorts' => [
                        [
                            'id' => 1,
                            'users' => ['2', '3', '4', '5'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/users', $request->getUri()->getPath());
                $this->assertEquals(
                    'filters[id][]=2&filters[id][]=3&filters[id][]=4&filters[id][]=5',
                    urldecode($request->getUri()->getQuery())
                );
                return new Response(200, [], json_encode([
                    'users' => [
                        [
                            'id' => 2,
                            'campusId' => 'xx1000002',
                            'enabled' => true,
                        ],
                        [
                            'id' => 3,
                            'campusId' => 'xx1000003',
                            'enabled' => true,
                        ],
                        [
                            'id' => 4,
                            'campusId' => 'xx1000004',
                            'enabled' => false, // Disabled user account - should result in user unenrolment.
                        ],
                        [
                            'id' => 5,
                            'campusId' => 'xx1000005', // Not currently enrolled - should result in new user enrolment.
                            'enabled' => true,
                        ],
                    ],
                ]));
            },
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        // Get a handle of the enrolment handler.
        $plugin = enrol_get_plugin('ilios');

        // Create a course and users, enrol some students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $user1 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000001']);
        $user2 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000002']);
        $user3 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000003']);
        $user4 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000004']);
        $user5 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000005']);

        $this->assertEquals(0, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $this->assertEquals(
            0,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(0, $DB->count_records('user_enrolments'));

        // Instantiate an enrolment instance that targets cohort members in Ilios.
        $plugin->add_instance($course, [
                'customint1' => 1, // Ilios cohort ID.
                'customint2' => 0, // Ilios learners enrolment.
                'customchar1' => 'cohort', // Enrol from cohort.
                'roleid' => $studentrole->id,
            ]
        );
        $this->assertEquals(1, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'ilios'], '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance->roleid);
        $this->assertEquals(1, $instance->customint1);
        $this->assertEquals(0, $instance->customint2);
        $this->assertEquals('cohort', $instance->customchar1);

        // Enable the enrolment method.
        $CFG->enrol_plugins_enabled = 'ilios';

        // Enroll users 1-4, but not 5.
        $plugin->enrol_user($instance, $user1->id, $studentrole->id);
        $plugin->enrol_user($instance, $user2->id, $studentrole->id);
        $plugin->enrol_user($instance, $user3->id, $studentrole->id);
        $plugin->enrol_user($instance, $user4->id, $studentrole->id);

        // Check user enrolments pre-sync.
        $this->assertEquals(
            4,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(4, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // Users 1 - 4 are actively enrolled in the course.
        foreach ([$user1, $user2, $user3, $user4] as $user) {
            $this->assertNotEmpty(
                $DB->get_record(
                    'role_assignments',
                    [
                        'roleid' => $studentrole->id,
                        'component' => 'enrol_ilios',
                        'userid' => $user->id,
                        'contextid' => $context->id,
                    ],
                    strictness: MUST_EXIST
                )
            );
            $userenrolment = $DB->get_record(
                    'user_enrolments',
                    [
                        'enrolid' => $instance->id,
                        'userid' => $user->id,
                    ],
                    strictness: MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
        }

        // Verify that user 5 is not enrolled.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user5->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEmpty(
            $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user5->id,
                ]
            )
        );

        // Run enrolment sync.
        $trace = new null_progress_trace();
        $plugin->sync($trace, null);

        // Check user enrolments post-sync.
        $this->assertEquals(
            3,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(4, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // User 2, 3, and now 5, are actively enrolled as students in the course.
        foreach ([$user2, $user3, $user5] as $user) {
            $this->assertNotEmpty(
                $DB->get_record(
                    'role_assignments',
                    [
                        'roleid' => $studentrole->id,
                        'component' => 'enrol_ilios',
                        'userid' => $user->id,
                        'contextid' => $context->id,
                    ],
                    strictness: MUST_EXIST
                )
            );
            $userenrolment = $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user->id,
                ],
                strictness: MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
        }
        // Verify that user1 has been fully unenrolled and has no role assigment in the course.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user1->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEmpty(
            $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user1->id,
                ]
            )
        );
        // User 4 is still enrolled in the course, but their enrolment status suspended and their student assignment
        // in the course has been removed.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user4->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $userenrolment = $DB->get_record(
            'user_enrolments',
            [
                'enrolid' => $instance->id,
                'userid' => $user4->id,
            ],
            strictness: MUST_EXIST
        );
        $this->assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status);
    }

    /**
     * Tests the enrolment of Ilios learner-group members into a Moodle course.
     */
    public function test_enrolment_from_ilios_learner_group_members(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Configure the Ilios API client.
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        // Mock out the responses from the Ilios API.
        $handlerstack = HandlerStack::create(new MockHandler([
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/learnergroups/1', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'learnerGroups' => [
                        [
                            'id' => 1,
                            'users' => ['2', '3', '4', '5'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/users', $request->getUri()->getPath());
                $this->assertEquals(
                    'filters[id][]=2&filters[id][]=3&filters[id][]=4&filters[id][]=5',
                    urldecode($request->getUri()->getQuery())
                );
                return new Response(200, [], json_encode([
                    'users' => [
                        [
                            'id' => 2,
                            'campusId' => 'xx1000002',
                            'enabled' => true,
                        ],
                        [
                            'id' => 3,
                            'campusId' => 'xx1000003',
                            'enabled' => true,
                        ],
                        [
                            'id' => 4,
                            'campusId' => 'xx1000004',
                            'enabled' => false, // Disabled user account - should result in user unenrolment.
                        ],
                        [
                            'id' => 5,
                            'campusId' => 'xx1000005', // Not currently enrolled - should result in new user enrolment.
                            'enabled' => true,
                        ],
                    ],
                ]));
            },

        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        // Get a handle of the enrolment handler.
        $plugin = enrol_get_plugin('ilios');

        // Create a course and users, enrol some students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $user1 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000001']);
        $user2 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000002']);
        $user3 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000003']);
        $user4 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000004']);
        $user5 = $this->getDataGenerator()->create_user(['idnumber' => 'xx1000005']);

        $this->assertEquals(0, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $this->assertEquals(
            0,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(0, $DB->count_records('user_enrolments'));

        // Instantiate an enrolment instance that targets cohort members in Ilios.
        $plugin->add_instance($course, [
                'customint1' => 1, // Ilios learner-group ID.
                'customint2' => 0, // Ilios learners enrolment.
                'customchar1' => 'learnerGroup', // Enrol from learner-group.
                'roleid' => $studentrole->id,
            ]
        );
        $this->assertEquals(1, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'ilios'], '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance->roleid);
        $this->assertEquals(1, $instance->customint1);
        $this->assertEquals(0, $instance->customint2);
        $this->assertEquals('learnerGroup', $instance->customchar1);

        // Enable the enrolment method.
        $CFG->enrol_plugins_enabled = 'ilios';

        // Enroll users 1-4, but not 5.
        $plugin->enrol_user($instance, $user1->id, $studentrole->id);
        $plugin->enrol_user($instance, $user2->id, $studentrole->id);
        $plugin->enrol_user($instance, $user3->id, $studentrole->id);
        $plugin->enrol_user($instance, $user4->id, $studentrole->id);

        // Check user enrolments pre-sync.
        $this->assertEquals(
            4,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(4, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // Users 1 - 4 are actively enrolled in the course.
        foreach ([$user1, $user2, $user3, $user4] as $user) {
            $this->assertNotEmpty(
                $DB->get_record(
                    'role_assignments',
                    [
                        'roleid' => $studentrole->id,
                        'component' => 'enrol_ilios',
                        'userid' => $user->id,
                        'contextid' => $context->id,
                    ],
                    strictness: MUST_EXIST
                )
            );
            $userenrolment = $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user->id,
                ],
                strictness: MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
        }

        // Verify that user 5 is not enrolled.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user5->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEmpty(
            $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user5->id,
                ]
            )
        );

        // Run enrolment sync.
        $trace = new null_progress_trace();
        $plugin->sync($trace, null);

        // Check user enrolments post-sync.
        $this->assertEquals(
            3,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(4, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // User 2, 3, and now 5, are actively enrolled as students in the course.
        foreach ([$user2, $user3, $user5] as $user) {
            $this->assertNotEmpty(
                $DB->get_record(
                    'role_assignments',
                    [
                        'roleid' => $studentrole->id,
                        'component' => 'enrol_ilios',
                        'userid' => $user->id,
                        'contextid' => $context->id,
                    ],
                    strictness: MUST_EXIST
                )
            );
            $userenrolment = $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user->id,
                ],
                strictness: MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
        }
        // Verify that user1 has been fully unenrolled and has no role assigment in the course.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user1->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEmpty(
            $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user1->id,
                ]
            )
        );
        // User 4 is still enrolled in the course, but their enrolment status suspended and their student assignment
        // in the course has been removed.
        $this->assertEmpty(
            $DB->get_record(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'userid' => $user4->id,
                    'contextid' => $context->id,
                ]
            )
        );
        $userenrolment = $DB->get_record(
            'user_enrolments',
            [
                'enrolid' => $instance->id,
                'userid' => $user4->id,
            ],
            strictness: MUST_EXIST
        );
        $this->assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status);
    }

    /**
     * Tests the enrolment of instructors to an Ilios learner-group (and its subgroups) into a Moodle course.
     */
    public function test_enrolment_from_ilios_learner_group_instructors(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Configure the Ilios API client.
        $accesstoken = helper::create_valid_ilios_api_access_token();
        set_config('apikey', $accesstoken, 'enrol_ilios');
        set_config('host_url', 'http://ilios.demo', 'enrol_ilios');

        // Mock out the responses from the Ilios API.
        $handlerstack = HandlerStack::create(new MockHandler([
            function(RequestInterface $request) {
                $this->assertEquals('/api/v3/learnergroups/1', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'learnerGroups' => [
                        [
                            'id' => 1,
                            'children' => ['2', '3'],
                            'ilmSessions' => ['1', '2'],
                            'offerings' => ['1', '2'],
                            'instructorGroups' => [],
                            'instructors' => [],
                        ],
                    ],
                ]));
            },
            // We're querying the entry-point learner group again, for no good reason.
            // Todo: Eliminate this duplication from the enrolment workflow [ST 2024/09/30].
            function(RequestInterface $request) {
                $this->assertEquals('/api/v3/learnergroups/1', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'learnerGroups' => [
                        [
                            'id' => 1,
                            'children' => ['2', '3'],
                            'ilmSessions' => ['1', '2'],
                            'offerings' => ['1', '2'],
                            'instructorGroups' => [],
                            'instructors' => [],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/offerings', $request->getUri()->getPath());
                $this->assertEquals(
                    'filters[id][]=1&filters[id][]=2',
                    urldecode($request->getUri()->getQuery())
                );
                return new Response(200, [], json_encode([
                'offerings' => [
                        [
                            'id' => 1,
                            'instructors' => [],
                            'instructorGroups' => ['1'],
                            'learners' => [],
                            'learnerGroups' => ['1'],
                        ],
                        [
                            'id' => 2,
                            'instructors' => ['1'],
                            'instructorGroups' => [],
                            'learners' => [],
                            'learnerGroups' => ['1'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/ilmsessions', $request->getUri()->getPath());
                $this->assertEquals(
                    'filters[id][]=1&filters[id][]=2',
                    urldecode($request->getUri()->getQuery())
                );
                return new Response(200, [], json_encode([
                    'ilmSessions' => [
                        [
                            'id' => 1,
                            'instructors' => [],
                            'instructorGroups' => ['1'],
                            'learners' => [],
                            'learnerGroups' => ['1'],
                        ],
                        [
                            'id' => 2,
                            'instructors' => ['1'],
                            'instructorGroups' => [],
                            'learners' => [],
                            'learnerGroups' => ['1'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/learnergroups/2', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'learnerGroups' => [
                        [
                            'id' => 2,
                            'children' => [],
                            'ilmSessions' => [],
                            'offerings' => ['3'],
                            'instructors' => ['2'],
                            'instructorGroups' => ['2'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/offerings', $request->getUri()->getPath());
                $this->assertEquals('filters[id][]=3', urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'offerings' => [
                        [
                            'id' => 3,
                            'instructors' => [],
                            'instructorGroups' => [],
                            'learners' => [],
                            'learnerGroups' => ['2'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/instructorgroups', $request->getUri()->getPath());
                $this->assertEquals('filters[id][]=2', urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'instructorGroups' => [
                        [
                            'id' => 2,
                            'users' => ['6', '7'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/learnergroups/3', $request->getUri()->getPath());
                return new Response(200, [], json_encode([
                    'learnerGroups' => [
                        [
                            'id' => 3,
                            'children' => [],
                            'ilmSessions' => ['3'],
                            'offerings' => [],
                            'instructors' => ['3'],
                            'instructorGroups' => ['3'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/ilmsessions', $request->getUri()->getPath());
                $this->assertEquals('filters[id][]=3', urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'ilmSessions' => [
                        [
                            'id' => 3,
                            'instructors' => [],
                            'instructorGroups' => [],
                            'learners' => [],
                            'learnerGroups' => [],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/instructorgroups', $request->getUri()->getPath());
                $this->assertEquals('filters[id][]=3', urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'instructorGroups' => [
                        [
                            'id' => 3,
                            'users' => ['8', '9'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/instructorgroups', $request->getUri()->getPath());
                $this->assertEquals('filters[id][]=1', urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'instructorGroups' => [
                        [
                            'id' => 1,
                            'users' => ['4', '5'],
                        ],
                    ],
                ]));
            },
            function (RequestInterface $request) {
                $this->assertEquals('/api/v3/users', $request->getUri()->getPath());
                $this->assertEquals(
                    'filters[id][]=1&filters[id][]=2&filters[id][]=3&filters[id][]=4'
                    . '&filters[id][]=5&filters[id][]=6&filters[id][]=7&filters[id][]=8&filters[id][]=9',
                    urldecode($request->getUri()->getQuery()));
                return new Response(200, [], json_encode([
                    'users' => array_map(
                        fn ($i) => ['id' => $i, 'campusId' => 'xx100000'. $i, 'enabled' => true ],
                        range(1, 9)
                    ),
                ]));
            },
        ]));
        di::set(http_client::class, new http_client(['handler' => $handlerstack]));

        // Get a handle of the enrolment handler.
        $plugin = enrol_get_plugin('ilios');

        // Create a course and users, enrol some students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $users = array_map(
            fn ($i) => $this->getDataGenerator()->create_user(['idnumber' => 'xx100000'. $i]),
            range(1, 9)
        );

        $this->assertEquals(0, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $this->assertEquals(
            0,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(0, $DB->count_records('user_enrolments'));

        // Instantiate an enrolment instance that targets cohort members in Ilios.
        $plugin->add_instance($course, [
                'customint1' => 1, // Ilios learner-group ID.
                'customint2' => 1, // Ilios Instructors enrolment.
                'customchar1' => 'learnerGroup', // Enrol from learner-group.
                'roleid' => $studentrole->id,
            ]
        );
        $this->assertEquals(1, $DB->count_records('enrol', ['enrol' => 'ilios']));
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'ilios'], '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance->roleid);
        $this->assertEquals(1, $instance->customint1);
        $this->assertEquals(1, $instance->customint2);
        $this->assertEquals('learnerGroup', $instance->customchar1);

        // Enable the enrolment method.
        $CFG->enrol_plugins_enabled = 'ilios';

        // Check user enrolments pre-sync.
        $this->assertEquals(
            0,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(0, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // Run enrolment sync.
        $trace = new null_progress_trace();
        $plugin->sync($trace, null);

        // Check user enrolments post-sync.
        $this->assertEquals(
            9,
            $DB->count_records(
                'role_assignments',
                [
                    'roleid' => $studentrole->id,
                    'component' => 'enrol_ilios',
                    'contextid' => $context->id,
                ]
            )
        );
        $this->assertEquals(9, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));

        // Check that all users have been enrolled.
        foreach ($users as $user) {
            $this->assertNotEmpty(
                $DB->get_record(
                    'role_assignments',
                    [
                        'roleid' => $studentrole->id,
                        'component' => 'enrol_ilios',
                        'userid' => $user->id,
                        'contextid' => $context->id,
                    ],
                    strictness: MUST_EXIST
                )
            );
            $userenrolment = $DB->get_record(
                'user_enrolments',
                [
                    'enrolid' => $instance->id,
                    'userid' => $user->id,
                ],
                strictness: MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
        }
    }
}
