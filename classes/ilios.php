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
 * The Ilios API client.
 *
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ilios;

use core\http_client;
use dml_exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use moodle_exception;

/**
 * The Ilios API client.
 *
 * @package    enrol_ilios
 * @copyright  The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ilios {

    /**
     * @var string The API base path.
     */
    const API_BASE_PATH = '/api/v3/';

    /**
     * @var string The API access token.
     */
    protected string $accesstoken;

    /**
     * @var string The Ilios API base URL.
     */
    protected string $apibaseurl;

    /**
     * Class constructor.
     * @param http_client $httpclient The HTTP client.
     * @throws dml_exception
     */
    public function __construct(
        /** @var http_client $httpclient The HTTP client */
        protected readonly http_client $httpclient
    ) {
        $this->accesstoken = get_config('enrol_ilios', 'apikey') ?: '';
        $hosturl = get_config('enrol_ilios', 'host_url') ?: '';
        $this->apibaseurl = rtrim($hosturl, '/') . self::API_BASE_PATH;
    }

    /**
     * Retrieves a list of schools from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of school objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_schools(array $filterby = [], array $sortby = []): array {
        return $this->get('schools', 'schools', $filterby, $sortby);
    }

    /**
     * Retrieves a list of cohorts from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of cohort objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_cohorts(array $filterby = [], array $sortby = []): array {
        return $this->get('cohorts', 'cohorts', $filterby, $sortby);
    }

    /**
     * Retrieves a list of programs from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of program objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_programs(array $filterby = [], array $sortby = []): array {
        return $this->get('programs', 'programs', $filterby, $sortby);
    }

    /**
     * Retrieves a list of program-years from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of program-year objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_program_years(array $filterby = [], array $sortby = []): array {
        return $this->get('programyears', 'programYears', $filterby, $sortby);
    }

    /**
     * Retrieves a list of learner-groups from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of learner-group objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_learner_groups(array $filterby = [], array $sortby = []): array {
        return $this->get('learnergroups', 'learnerGroups', $filterby, $sortby);
    }

    /**
     * Retrieves a list of instructor-groups from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of instructor-group objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_instructor_groups(array $filterby = [], array $sortby = []): array {
        return $this->get('instructorgroups', 'instructorGroups', $filterby, $sortby);
    }

    /**
     * Retrieves a list of offerings from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of offering objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_offerings(array $filterby = [], array $sortby = []): array {
        return $this->get('offerings', 'offerings', $filterby, $sortby);
    }

    /**
     * Retrieves a list of ILMs from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of ILM objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_ilms(array $filterby = [], array $sortby = []): array {
        return $this->get('ilmsessions', 'ilmSessions', $filterby, $sortby);
    }

    /**
     * Retrieves a list of users from Ilios.
     *
     * @param array $filterby An associative array of filtering criteria.
     * @param array $sortby An associative array of sorting criteria.
     * @return array A list of user objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_users(array $filterby = [], array $sortby = []): array {
        return $this->get('users', 'users', $filterby, $sortby);
    }

    /**
     * Retrieves a school by its ID from Ilios.
     *
     * @param int $id
     * @return object|null The school object, or NULL if not found.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_school(int $id): ?object {
        return $this->get_by_id('schools', 'schools', $id);
    }

    /**
     * Retrieves a cohort by its ID from Ilios.
     *
     * @param int $id
     * @return object|null The cohort object, or NULL if not found.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_cohort(int $id): ?object {
        return $this->get_by_id('cohorts', 'cohorts', $id);
    }

    /**
     * Retrieves a program by its ID from Ilios.
     *
     * @param int $id
     * @return object|null The program object, or NULL if not found.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_program(int $id): ?object {
        return $this->get_by_id('programs', 'programs', $id);
    }

    /**
     * Retrieves a learner-group by its ID from Ilios.
     *
     * @param int $id
     * @return object|null The learner-group object, or NULL if not found.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_learner_group(int $id): ?object {
        return $this->get_by_id('learnergroups', 'learnerGroups', $id);
    }

    /**
     * Retrieves a list instructors for a given learner-group and its subgroups.
     *
     * @param int $groupid The group ID.
     * @return array A list of user IDs.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_instructor_ids_from_learner_group(int $groupid): array {
        $group = $this->get_learner_group($groupid);
        // No group, no instructors.
        if (empty($group)) {
            return [];
        }

        $instructorgroupids = [];
        $instructorids = [];

        // Get instructors/instructor-groups from the offerings that this learner group is being taught in.
        if (!empty($group->offerings)) {
            $offerings = $this->get_offerings(['id' => $group->offerings]);

            foreach ($offerings as $offering) {
                if (empty($offering->instructors) && empty($offering->instructorGroups)) {
                    // No instructor AND no instructor groups have been set for this offering.
                    // Fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorids = array_merge($instructorids, $group->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $group->instructorGroups);
                } else {
                    // If there are instructors and/or instructor-groups set on the offering, then use these.
                    $instructorids = array_merge($instructorids, $offering->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $offering->instructorGroups);
                }
            }
        }

        // Get instructors/instructor-groups from the ilm sessions that this learner group is being taught in.
        // This is a rinse/repeat from offerings-related code above.
        if (!empty($group->ilmSessions)) {
            $ilms = $this->get_ilms(['id' => $group->ilmSessions]);

            foreach ($ilms as $ilm) {
                if (empty($ilm->instructors) && empty($ilm->instructorGroups)) {
                    // No instructor AND no instructor groups have been set for this offering.
                    // Fall back to the default instructors/instructor-groups defined for the learner group.
                    $instructorids = array_merge($instructorids, $group->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $group->instructorGroups);
                } else {
                    // If there are instructors and/or instructor-groups set on the offering, then use these.
                    $instructorids = array_merge($instructorids, $ilm->instructors);
                    $instructorgroupids = array_merge($instructorgroupids, $ilm->instructorGroups);
                }
            }
        }

        // Get instructors from sub-learner-groups.
        if (!empty($group->children)) {
            foreach ($group->children as $subgroupid) {
                $instructorids = array_merge(
                    $instructorids,
                    $this->get_instructor_ids_from_learner_group($subgroupid)
                );
                // We don't care about instructor groups here,
                // we will merge instructor groups into the $instructorIds array later.
            }
        }

        // Next, get the ids of all instructors from the instructor-groups that we determined as relevant earlier.
        // But first let's de-dupe them.
        $instructorgroupids = array_unique($instructorgroupids);
        if (!empty($instructorgroupids)) {
            $instructorgroups = $this->get_instructor_groups(['id' => $instructorgroupids]);
            foreach ($instructorgroups as $instructorgroup) {
                $instructorids = array_merge($instructorids, $instructorgroup->users);
            }
        }

        // Finally, we retrieve all the users that were identified as relevant instructors earlier.
        $instructorids = array_unique($instructorids);
        asort($instructorids);
        return array_values($instructorids);
    }


    /**
     * Sends a GET request to a given API endpoint with given options.
     *
     * @param string $path The target path fragment of the API request URL. May include query parameters.
     * @param string $key The name of the property that holds the requested data points in the payload.
     * @param array $filterby An associative array of filter options.
     * @param array $sortby An associative array of sort options.
     * @return array The data points from the decoded payload.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get(
        string $path,
        string $key,
        array $filterby = [],
        array $sortby = [],
    ): array {
        $this->validate_access_token($this->accesstoken);
        $options = ['headers' => ['X-JWT-Authorization' => 'Token ' . $this->accesstoken]];

        // Construct query params from given filters and sort orders.
        // Unfortunately, <code>http_build_query()</code> doesn't cut it here, so we have to hand-roll this.
        $queryparams = [];
        if (!empty($filterby)) {
            foreach ($filterby as $param => $value) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $queryparams[] = "filters[$param][]=$val";
                    }
                } else {
                    $queryparams[] = "filters[$param]=$value";
                }
            }
        }

        if (!empty($sortby)) {
            foreach ($sortby as $param => $value) {
                $queryparams[] = "order_by[$param]=$value";
            }
        }

        $url = $this->apibaseurl . $path;

        if (!empty($queryparams)) {
            $url .= '?' . implode('&', $queryparams);
        }

        $response = $this->httpclient->get($url, $options);
        $rhett = $this->parse_result($response->getBody());
        if (!property_exists($rhett, $key)) {
            throw new moodle_exception(
                'errorresponseentitynotfound',
                'enrol_ilios',
                a: $key,
            );
        }
        return $rhett->$key;
    }

    /**
     * Decodes and retrieves the payload of the given access token.
     *
     * @param string $accesstoken the Ilios API access token
     * @return array the token payload as key/value pairs.
     * @throws moodle_exception
     */
    public static function get_access_token_payload(string $accesstoken): array {
        $parts = explode('.', $accesstoken);
        if (count($parts) !== 3) {
            throw new moodle_exception('errorinvalidnumbertokensegments', 'enrol_ilios');
        }
        $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);
        if (!$payload) {
            throw new moodle_exception('errordecodingtoken', 'enrol_ilios');
        }
        return $payload;
    }

    /**
     * Retrieves a given resource from Ilios by its given ID.
     *
     * @param string $path The URL path fragment that names the resource.
     * @param string $key The name of the property that holds the requested data points in the payload.
     * @param int $id The ID.
     * @param bool $returnnullonnotfound If TRUE then NULL is returned if the resource cannot be found.
     *                                      On FALSE, an exception is raised on 404/Not-Found.
     *                                      Defaults to TRUE.
     * @return object|null The resource object, or NULL.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_by_id(string $path, string $key, int $id, bool $returnnullonnotfound = true): ?object {
        try {
            $response = $this->get($path . '/' . $id, $key);
            return $response[0];
        } catch (ClientException $e) {
            if ($returnnullonnotfound && (404 === $e->getResponse()->getStatusCode())) {
                return null;
            }
            // Re-throw the exception otherwise.
            throw $e;
        }
    }

    /**
     * Decodes and returns the given JSON-encoded input.
     *
     * @param string $str A JSON-encoded string
     * @return object The JSON-decoded object representation of the given input.
     * @throws moodle_exception
     */
    protected function parse_result(string $str): object {
        if (empty($str)) {
            throw new moodle_exception('erroremptyresponse', 'enrol_ilios');
        }
        $result = json_decode($str);

        if (empty($result)) {
            throw new moodle_exception('errordecodingresponse', 'enrol_ilios');
        }

        if (isset($result->errors)) {
            throw new moodle_exception(
                'errorresponsewitherror',
                'enrol_ilios',
                '',
                (string) $result->errors[0],
            );
        }

        return $result;
    }

    /**
     * Validates the given access token.
     * Will throw an exception if the token is not valid - that happens if the token is not set, cannot be decoded, or is expired.
     *
     * @param string $accesstoken the Ilios API access token
     * @return void
     * @throws moodle_exception
     */
    protected function validate_access_token(string $accesstoken): void {
        // Check if token is blank.
        if ('' === trim($accesstoken)) {
            throw new moodle_exception('erroremptytoken', 'enrol_ilios');
        }

        // Decode token payload. will throw an exception if this fails.
        $tokenpayload = self::get_access_token_payload($accesstoken);

        // Check if token is expired.
        if ($tokenpayload['exp'] < time()) {
            throw new moodle_exception('errortokenexpired', 'enrol_ilios');
        }
    }
}
