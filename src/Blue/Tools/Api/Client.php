<?php

namespace Blue\Tools\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\modify_request;
use function GuzzleHttp\Psr7\parse_query;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class Client
{
    //--------------------
    // Constants
    //--------------------

    /** @var int */
    public static $VERSION = 2;

    /** @var string */
    public static $AUTH_TYPE = 'bsdtools_v2';

    //--------------------
    // Credentials
    //--------------------

    /** @var string */
    private $id;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $secret;

    //--------------------
    // Configuration
    //--------------------

    /** @var int */
    private $deferredResultMaxAttempts = 20;

    /** @var int */
    private $deferredResultInterval = 5;

    //--------------------
    // Other internals
    //--------------------

    /** @var LoggerInterface */
    private $logger;

    /** @var GuzzleClient */
    private $guzzleClient;

    /**
     * @param string $id
     * @param string $secret
     * @param string $url
     */
    public function __construct($id, $secret, $url)
    {
        $this->logger = new NullLogger();
        if (!strlen($id) || !strlen($secret)) {
            throw new InvalidArgumentException('api_id and api_secret must both be provided');
        }

        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        if (!$validatedUrl) {
            throw new InvalidArgumentException($url.' is not a valid URL');
        }

        $this->id = $id;
        $this->secret = $secret;
        $this->baseUrl = $validatedUrl.'/page/api/';

        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::mapRequest(
            function (RequestInterface $request) {
                $uri = $request->getUri();

                /** @var array $query */
                $query = parse_query($request->getUri()->getQuery());

                /*
                 * Add id and version to the query
                 */
                $query['api_id'] = $this->id;
                $query['api_ver'] = 2;

                /*
                 * Add timestamp to the query
                 */
                if (!isset($query['api_ts'])) {
                    $query['api_ts'] = time();
                }

                $mac = $this->generateMac($uri->getPath(), build_query($query));

                $query['api_mac'] = $mac;

                return modify_request($request, ['query' => build_query($query)]);
            }
        ));
        $this->guzzleClient = new GuzzleClient(
            [
                'handler' => $handlerStack,
            ]
        );
    }

    /**
     * Execute a GET request against the API.
     *
     * @param string $apiPath
     * @param array  $queryParams
     *
     * @return ResponseInterface
     */
    public function get($apiPath, $queryParams = [])
    {
        $response = $this->guzzleClient->get(
            $this->baseUrl.$apiPath,
            [
                'query'  => $queryParams,
                'future' => false,
            ]
        );

        return $this->resolve($response);
    }

    /**
     * Execute a POST request against the API.
     *
     * @param $apiPath
     * @param array  $queryParams
     * @param string $data
     *
     * @return ResponseInterface
     */
    public function post($apiPath, $queryParams = [], $data = '')
    {
        $response = $this->guzzleClient->post(
            $this->baseUrl.$apiPath,
            [
                'query'  => $queryParams,
                'body'   => $data,
                'future' => false,
            ]
        );

        return $this->resolve($response);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function resolve(ResponseInterface $response)
    {

        // An HTTP status of 202 indicates that this request was deferred
        if ($response->getStatusCode() == 202) {
            $key = $response->getBody()->getContents();

            $attempts = $this->deferredResultMaxAttempts;

            while ($attempts > 0) {
                /** @var ResponseInterface $deferredResponse */
                $deferredResponse = $this->guzzleClient->get(
                    $this->baseUrl.'get_deferred_results',
                    [
                        'future' => false,
                        'query'  => [
                            'deferred_id' => $key,
                        ],
                    ]
                );

                if ($deferredResponse->getStatusCode() != 202) {
                    return $deferredResponse;
                }

                sleep($this->deferredResultInterval);
                $attempts--;
            }

            throw new RuntimeException('Could not load deferred response '.
                "after {$this->deferredResultMaxAttempts} attempts");
        }

        // If the request was not deferred, then return as-is
        return $response;
    }

    /**
     * Creates a hash based on request parameters.
     *
     * @param string $path
     * @param string $query
     *
     * @return string
     */
    private function generateMac($path, $queryString)
    {
        $query = parse_query($queryString);

        // combine strings to build the signing string
        $apiId = $query['api_id'];
        $apiTs = $query['api_ts'];
        $signingString = $apiId."\n"
            .$apiTs."\n"
            .$path."\n"
            .urldecode($queryString);
        $mac = hash_hmac('sha1', $signingString, $this->secret);
        return $mac;
    }

    /**
     * @param int $deferredResultMaxAttempts
     */
    public function setDeferredResultMaxAttempts($deferredResultMaxAttempts)
    {
        $this->deferredResultMaxAttempts = $deferredResultMaxAttempts;
    }

    /**
     * @param int $deferredResultInterval
     */
    public function setDeferredResultInterval($deferredResultInterval)
    {
        $this->deferredResultInterval = $deferredResultInterval;
    }

    /**
     * @return GuzzleClient
     */
    public function getGuzzleClient()
    {
        return $this->guzzleClient;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns the specified request option or all options if none specified
     * @param null $keyOrPath
     * @return array|mixed|null
     */
    public function getRequestOption($keyOrPath = null)
    {
        return $this->guzzleClient->getDefaultOption($keyOrPath);
    }

    /**
     * Sets a request option for future requests
     * @param $keyOrPath
     * @param $value
     * @return $this
     */
    public function setRequestOption($keyOrPath, $value)
    {
        $this->guzzleClient->setDefaultOption($keyOrPath, $value);
        return $this;
    }
}
