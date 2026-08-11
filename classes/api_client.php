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
 * API client class for AI Essay Maker plugin.
 *
 * Handles all communication with the Essay Grader AI API using Moodle's
 * curl wrapper for reliable HTTP requests.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizmaker;

defined('MOODLE_INTERNAL') || die();

/**
 * API client for communicating with Essay Grader AI service.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** @var string Base URL for the API */
    const API_BASE_URL = 'https://lms-labs.com/api';

    /** @var string Site identifier */
    private $siteid;

    /** @var string API key */
    private $apikey;

    /**
     * Constructor.
     *
     * @param string $siteid The site identifier.
     * @param string $apikey The API key.
     */
    public function __construct(string $siteid, string $apikey) {
        $this->siteid = $siteid;
        $this->apikey = $apikey;
    }

    /**
     * Create a new API client instance from plugin configuration.
     *
     * @return api_client|null Returns null if not configured.
     */
    public static function create_from_config(): ?api_client {
        global $CFG;
        
        // Explicitly include aiconfig lib.php if available
        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }
        
        // Priority 1: Central Config (recommended for multi-plugin setups)
        $siteid = '';
        $apikey = '';
        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = local_aiconfig_get_siteid();
        }
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = local_aiconfig_get_apikey();
        }
        
        // Priority 2: Plugin settings as fallback
        if (empty($siteid)) {
            $siteid = get_config('local_aiquizmaker', 'siteid');
        }
        if (empty($apikey)) {
            $apikey = get_config('local_aiquizmaker', 'apikey');
        }

        if (empty($siteid) || empty($apikey)) {
            return null;
        }

        return new self($siteid, $apikey);
    }

    /**
     * Make an HTTP request using Moodle's curl wrapper.
     *
     * @param string $url The URL to request.
     * @param bool $post Whether to use POST method.
     * @param array|null $payload The payload for POST requests.
     * @return array Response array with 'success' boolean and 'data' or 'error'.
     */
    private function make_request(string $url, bool $post = false, ?array $payload = null): array {
        $curl = new \curl();
        // Use CURLOPT constants (not strings) for Moodle's curl wrapper to recognize them
        $curl->setopt([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FAILONERROR => false, // Handle HTTP errors ourselves
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($post && $payload) {
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];
            $curl->setHeader($headers);
            $body = $curl->post($url, json_encode($payload));
        } else {
            $curl->setHeader(['Accept: application/json']);
            $body = $curl->get($url);
        }

        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? $info['http_code'] : 0;
        $error = $curl->get_errno() ? $curl->error : null;

        if ($body === false || $httpcode === 0) {
            return [
                'success' => false,
                'error' => $error ? $error : get_string('error_connection', 'local_aiquizmaker'),
                'httpcode' => $httpcode,
            ];
        }

        $decoded = json_decode($body, true);
        if ($decoded === null) {
            return [
                'success' => false,
                'error' => get_string('error_invalid_response', 'local_aiquizmaker'),
                'httpcode' => $httpcode,
            ];
        }

        return [
            'success' => true,
            'data' => $decoded,
            'httpcode' => $httpcode,
        ];
    }

    /**
     * Make a GET request to the API.
     *
     * @param string $endpoint The API endpoint (e.g., '/credits').
     * @return array Response array with 'success' boolean and 'data' or 'error'.
     */
    public function get(string $endpoint): array {
        $url = self::API_BASE_URL . $endpoint;
        $url .= '?siteId=' . urlencode($this->siteid);
        $url .= '&apiKey=' . urlencode($this->apikey);

        return $this->make_request($url, false);
    }

    /**
     * Make a POST request to the API.
     *
     * @param string $endpoint The API endpoint (e.g., '/generate-essays').
     * @param array $data The data to send in the request body.
     * @return array Response array with 'success' boolean and 'data' or 'error'.
     */
    public function post(string $endpoint, array $data = []): array {
        $url = self::API_BASE_URL . $endpoint;

        // Add credentials to data for POST requests.
        $data['siteId'] = $this->siteid;
        $data['apiKey'] = $this->apikey;

        return $this->make_request($url, true, $data);
    }

    /**
     * Get the current credit balance.
     *
     * @return array Response with 'credits' key on success.
     */
    public function get_credits(): array {
        return $this->get('/credits');
    }

    /**
     * Get available industries list.
     *
     * @return array Response with 'industries' array on success.
     */
    public function get_industries(): array {
        return $this->get('/industries');
    }

    /**
     * Generate essay questions.
     *
     * @param array $criteria Array of criteria with text and questionsCount.
     * @param string $country Country name.
     * @param string $state State/region (optional).
     * @param string $industry Industry name.
     * @param string $industrydetails Industry details (optional).
     * @param string $jobtitle Job title.
     * @param string $joblevel Job level.
     * @return array Response with 'questions' array on success.
     */
    public function generate_essays(
        array $criteria,
        string $country,
        string $state,
        string $industry,
        string $industrydetails,
        string $jobtitle,
        string $joblevel
    ): array {
        return $this->post('/generate-essays', [
            'criteria' => $criteria,
            'country' => $country,
            'state' => $state,
            'industry' => $industry,
            'industryDetails' => $industrydetails,
            'jobTitle' => $jobtitle,
            'jobLevel' => $joblevel,
        ]);
    }
}
