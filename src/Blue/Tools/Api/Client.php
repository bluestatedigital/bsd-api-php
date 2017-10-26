<?php

namespace Blue\Tools\Api;


use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
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


        $handlerStack->push(
            function(callable $handler) {
                return new SigningHandler($handler);
            },
            self::$AUTH_TYPE . '_auth'
        );
        $this->guzzleClient = new GuzzleClient(
            [
                'handler' => $handlerStack,
                'auth' => [
                    $this->id,
                    $this->secret,
                    self::$AUTH_TYPE
                ]
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
     * Returns the specified request option or all options if none specified.
     *
     * @param null $keyOrPath
     *
     * @return array|mixed|null
     */
    public function getRequestOption($keyOrPath = null)
    {
        return $this->guzzleClient->getConfig($keyOrPath);
    }

    /**
     * Sets a request option for future requests.
     *
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function setRequestOption($key, $value)
    {
        $config = array_merge(
            $this->guzzleClient->getConfig(),
            [$key => $value]
        );

        $this->guzzleClient = new GuzzleClient($config);
        return $this;
    }
}
