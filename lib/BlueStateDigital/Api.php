<?php
/**
 * Copyright 2013 Blue State Digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class BlueStateDigital_Api
{
    protected $api_id;
    protected $api_secret;
    protected $http_request_base;

    public $deferred_result_call_interval = 5; // in seconds
    public $deferred_result_call_max_attempts = 20;

    const HTTP_CODE_OK                          = 200;
    const HTTP_CODE_DEFERRED_RESULT             = 202;
    const HTTP_CODE_DEFERRED_RESULT_EMPTY       = 204;
    const HTTP_CODE_DEFERRED_RESULT_COMPILING   = 503;

    const API_VER = 2;

    public function __construct($api_id, $api_secret, $api_url)
    {
        if (!strlen($api_id) || !strlen($api_secret)) {
            throw new InvalidArgumentException('api_id and api_secret must both be provided');
        }

        $validated_url = filter_var($api_url, FILTER_VALIDATE_URL);
        if (!$validated_url) {
            throw new InvalidArgumentException($api_url . ' is not a valid URL');
        }

        $this->api_id = $api_id;
        $this->api_secret = $api_secret;
        $this->http_request_base = $validated_url . '/page/api/';
    }

    public function get($url, $query_params = array())
    {
        // prepend URL with base path for the API
        $url = $this->http_request_base . $url;

        // add api_id, timestamp, and version number to query string
        $query_params['api_id'] = $this->api_id;
        if (!array_key_exists('api_ts', $query_params)) {
            $query_params['api_ts'] = time();
        }
        $query_params['api_ver'] = self::API_VER;

        // add api_mac to query string after using existing query and request url to build
        // the api_mac
        $query_params['api_mac'] = $this->_buildApiMac($url, $query_params);

        // add query string to request URL
        $url .= '?' . http_build_query($query_params);

        // create new request object and pass it connection options
        $client = new Horde_Http_Client(array(
            'request.timeout' => 10,
            'request.redirects' => 3,
        ));

        // send request to API url
        $result = $client->get($url);

        // is this a deferred result?
        if ($result->code == self::HTTP_CODE_DEFERRED_RESULT) {
            // reroute this request for more processing. the _deferredResult function should
            // return a new HTTP_Request object with the actual requested content
            $result = $this->_deferredResult($result->getBody());
        }

        return $result;
    }

    public function post($url, $query_params = array(), $post_data = '')
    {
        // prepend URL with base path for the API
        $url = $this->http_request_base . $url;

        // add api_id, timestamp, and version number to query string
        $query_params['api_id'] = $this->api_id;
        if (!array_key_exists('api_ts', $query_params)) {
            $query_params['api_ts'] = time();
        }
        $query_params['api_ver'] = self::API_VER;

        // add api_mac to query string after using existing query and request url to build
        // the api_mac
        $query_params['api_mac'] = $this->_buildApiMac($url, $query_params);

        // add query string to request URL
        $url .= '?' . http_build_query($query_params);

        // create new request object and pass it connection options
        $client = new Horde_Http_Client(array(
            'request.timeout' => 10,
            'request.redirects' => 1,
        ));

        // send request to API url
        $result = $client->post($url, $post_data);

        // is this a deferred result?
        if ($result->code == self::HTTP_CODE_DEFERRED_RESULT) {
            // reroute this request for more processing. the _deferredResult function should
            // return a new HTTP_Request object with the actual requested content
            $result = $this->_deferredResult($result->getBody());
        }

        return $result;
    }

    public function put($url, $query_params = array(), $post_data = '')
    {
    }

    protected function _buildApiMac($url, $query)
    {
        // break URL into parts to get the path
        $url_parts = parse_url($url);

        // trim double slashes in the path
        if (substr($url_parts['path'], 0, 2) == '//') {
            $url_parts['path'] = substr($url_parts['path'], 1);
        }

        // build query string from given parameters
        $query_string = urldecode(http_build_query($query));

        // combine strings to build the signing string
        $signing_string = $query['api_id'] . "\n" .
            $query['api_ts'] . "\n" .
            $url_parts['path'] . "\n" .
            $query_string;
        var_dump($signing_string); exit;

        return hash_hmac('sha1', $signing_string, $this->api_secret);
    }

    protected function _deferredResult($deferred_id)
    {
        $attempt = 0;

        // loop until result is ready or until we give up
        do {
            // delay between calls (in seconds)
            sleep($this->deferred_result_call_interval);

            // check to see if result is ready
            $result = $this->get('get_deferred_results', array('deferred_id' => $deferred_id));

            // increment attempts counter
            $attempt++;
        } while ($result->code == self::HTTP_CODE_DEFERRED_RESULT_COMPILING && $attempt < $this->deferred_result_call_max_attempts);

        // if the response code isn't HTTP_CODE_OK then we didn't get the result we wanted
        if (!in_array($result->code, array(self::HTTP_CODE_OK, self::HTTP_CODE_DEFERRED_RESULT_EMPTY))) {
            // did we go over our "max attempts"?
            if ($attempt >= $this->deferred_result_call_max_attempts) {
                throw new Exception('Could not retrieve deferred result.  Max attempts reached.', 1);
            }
            // we must have received an unexpected HTTP code
            else {
                throw new Exception('Could not retrieve deferred result.  HTTP Code ' .
                    $result->code . ' was returned, with the following message: ' .
                    $result->getBody(), 2);
            }
        }

        // return request result
        return $result;
    }
}
