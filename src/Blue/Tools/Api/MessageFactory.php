<?php
namespace Blue\Tools\Api;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Url;
use RuntimeException;

class MessageFactory extends \GuzzleHttp\Message\MessageFactory
{

    /**
     * Create a new request based on the HTTP method.
     *
     * This method accepts an associative array of request options. Below is a
     * brief description of each parameter. See
     * http://docs.guzzlephp.org/clients.html#request-options for a much more
     * in-depth description of each parameter.
     *
     * - headers: Associative array of headers to add to the request
     * - body: string|resource|array|StreamInterface request body to send
     * - json: mixed Uploads JSON encoded data using an application/json Content-Type header.
     * - query: Associative array of query string values to add to the request
     * - auth: array|string HTTP auth settings (user, pass[, type="basic"])
     * - version: The HTTP protocol version to use with the request
     * - cookies: true|false|CookieJarInterface To enable or disable cookies
     * - allow_redirects: true|false|array Controls HTTP redirects
     * - save_to: string|resource|StreamInterface Where the response is saved
     * - events: Associative array of event names to callables or arrays
     * - subscribers: Array of event subscribers to add to the request
     * - exceptions: Specifies whether or not exceptions are thrown for HTTP protocol errors
     * - timeout: Timeout of the request in seconds. Use 0 to wait indefinitely
     * - connect_timeout: Number of seconds to wait while trying to connect. (0 to wait indefinitely)
     * - verify: SSL validation. True/False or the path to a PEM file
     * - cert: Path a SSL cert or array of (path, pwd)
     * - ssl_key: Path to a private SSL key or array of (path, pwd)
     * - proxy: Specify an HTTP proxy or hash of protocols to proxies
     * - debug: Set to true or a resource to view handler specific debug info
     * - stream: Set to true to stream a response body rather than download it all up front
     * - expect: true/false/integer Controls the "Expect: 100-Continue" header
     * - config: Associative array of request config collection options
     * - decode_content: true/false/string to control decoding content-encoding responses
     *
     * @param string $method HTTP method (GET, POST, PUT, etc.)
     * @param string|Url $url HTTP URL to connect to
     * @param array $options Array of options to apply to the request
     *
     * @return RequestInterface
     * @link http://docs.guzzlephp.org/clients.html#request-options
     */
    public function createRequest($method, $url, array $options = [])
    {
        $request = parent::createRequest($method, $url, $options);

        $query =$request->getQuery();

        $auth = $request->getConfig()->get('auth');

        // The 'auth' configuration must be valid
        if ((!is_array($auth)) || (count($auth) < 3) || ($auth[2] != Client::$AUTH_TYPE)) {
            throw new RuntimeException("Authorization information not provided");
        }

        $id = $auth[0];
        $secret = $auth[1];

        // Add API User ID to the query
        $query->set('api_id', $id);

        // Add timestamp to the query
        if (!$query->hasKey('api_ts')) {
            $query->set('api_ts', time());
        }

        // Add version to the query
        $query->set('api_ver', '2');

        // Add hash to the query
        $hash = $this->generateMac($request->getUrl(), $request->getQuery()->toArray(), $secret);
        $query->set('api_mac', $hash);

        return $request;
    }


    /**
     * Creates a hash based on request parameters
     *
     * @param string $url
     * @param array $query
     * @param string $secret
     * @return string
     */
    private function generateMac($url, $query, $secret)
    {
        // break URL into parts to get the path
        $urlParts = parse_url($url);

        // trim double slashes in the path
        if (substr($urlParts['path'], 0, 2) == '//') {
            $urlParts['path'] = substr($urlParts['path'], 1);
        }

        // build query string from given parameters
        $queryString = urldecode(http_build_query($query));

        // combine strings to build the signing string
        $signingString = $query['api_id'] . "\n" .
            $query['api_ts'] . "\n" .
            $urlParts['path'] . "\n" .
            $queryString;

        $mac = hash_hmac('sha1', $signingString, $secret);


        return $mac;
    }


    /**
     * Creates a response
     *
     * @param string $statusCode HTTP status code
     * @param array $headers Response headers
     * @param mixed $body Response body
     * @param array $options Response options
     *     - protocol_version: HTTP protocol version
     *     - header_factory: Factory used to create headers
     *     - And any other options used by a concrete message implementation
     *
     * @return ResponseInterface
     */
    public function createResponse(
        $statusCode,
        array $headers = [],
        $body = null,
        array $options = []
    )
    {
        return parent::createResponse($statusCode, $headers, $body, $options);
    }
}