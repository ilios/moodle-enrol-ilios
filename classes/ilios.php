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
     * Retrieves all schools from Ilios.
     *
     * @return array A list of school objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_schools(): array {
        $response = $this->get('schools');
        return $response->schools;
    }

    /**
     * Retrieves all enabled users with a given primary school affiliation.
     *
     * @param int $schoolid The school ID.
     * @return array A list of user objects.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get_enabled_users_in_school(int $schoolid): array {
        $response = $this->get('users?filters[enabled]=true&filters[school]=' . $schoolid);
        return $response->users;
    }


    /**
     * Sends a GET request to a given API endpoint with given options.
     *
     * @param string $path The target path fragment of the API request URL. May include query parameters.
     * @param array $options Additional options.
     * @return object The decoded response body.
     * @throws GuzzleException
     * @throws moodle_exception
     */
    public function get(string $path, array $options = []): object {
        $this->validate_access_token($this->accesstoken);

        if (!array_key_exists('headers', $options) || empty($options['headers'])) {
            $options = array_merge($options, ['headers' => [
                'X-JWT-Authorization' => 'Token ' . $this->accesstoken,
            ]]);
        }
        $response = $this->httpclient->get($this->apibaseurl . $path, $options);
        return $this->parse_result($response->getBody());
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
}
