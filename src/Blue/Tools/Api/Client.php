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

    /** @var array */
    private $requestOptions;

    /**
     * @param string $id
     * @param string $secret
     * @param string $url
     * @param array $requestOptions
     */
    public function __construct($id, $secret, $url, array $requestOptions = [])
    {
        $this->logger = new NullLogger();

        if (!strlen($id) || !strlen($secret)) {
            throw new InvalidArgumentException('api_id and api_secret must both be provided');
        }

        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        if (!$validatedUrl) {
            throw new InvalidArgumentException($url . ' is not a valid URL');
        }

        $this->id = $id;
        $this->secret = $secret;
        $this->baseUrl = $validatedUrl . '/page/api/';

        $this->guzzleClient = $this->createGuzzleClient(null, $requestOptions);
    }

    /**
     * @param null|callable $baseHandler
     * @param array $options
     * @return \GuzzleHttp\Client
     */
    protected function createGuzzleClient($baseHandler = null, array $options = [])
    {
        $stack = HandlerStack::create($baseHandler);

        $stack->push(function (callable $handler) {
            return new SigningHandler($handler);
        }, 'sign_request');

        $options['handler'] = $stack;

        return new GuzzleClient($options);
    }

    /**
     * Execute a GET request against the API
     *
     * @param string $apiPath
     * @param array $queryParams
     * @return ResponseInterface
     */
    public function get($apiPath, $queryParams = [])
    {
        $response = $this->guzzleClient->get(
            $this->baseUrl . $apiPath,
            [
                'query' => $queryParams,
                'future' => false,
                'auth' => [
                    $this->id,
                    $this->secret,
                    self::$AUTH_TYPE,
                ],
            ]
        );

        return $this->resolve($response);
    }

    /**
     * Execute a POST request against the API
     *
     * @param $apiPath
     * @param array $queryParams
     * @param string $data
     * @return ResponseInterface
     */
    public function post($apiPath, $queryParams = [], $data = '')
    {

        $response = $this->guzzleClient->post(
            $this->baseUrl . $apiPath,
            [
                'query' => $queryParams,
                'body' => $data,
                'future' => false,
                'auth' => [
                    $this->id,
                    $this->secret,
                    self::$AUTH_TYPE,
                ],
            ]
        );

        return $this->resolve($response);
    }

    /**
     * @param callable $handler
     * @param array $options
     * @return $this
     */
    public function withHandler(callable $handler, array $options = [])
    {
        $this->guzzleClient = $this->createGuzzleClient($handler, $options);

        return $this;
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function resolve(ResponseInterface $response)
    {

        // An HTTP status of 202 indicates that this request was deferred
        if ($response->getStatusCode() == 202) {

            $content = $response->getBody()->getContents();
            $res = json_decode($content);
            if (json_last_error() != JSON_ERROR_NONE) {
                $key = $content;
            }
            else if(isset($res->mailing_triggered_id)) {
                // The send_triggered_email API uses 202 to indicate something other than a traditional deferred result.
                // Use get_triggered_send to verify the status of a triggered email.
                return $response;
            }
            else {
                $key = $content;
            }

            $attempts = $this->deferredResultMaxAttempts;

            while ($attempts > 0) {
                /** @var ResponseInterface $deferredResponse */
                $deferredResponse = $this->guzzleClient->get(
                    $this->baseUrl . "get_deferred_results",
                    [
                        'auth' => [
                            $this->id,
                            $this->secret,
                            self::$AUTH_TYPE,
                        ],
                        'future' => false,
                        'query' => [
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

            throw new RuntimeException("Could not load deferred response after {$this->deferredResultMaxAttempts} attempts");
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
     * Returns the specified request option or all options if none specified
     * @param $keyOrPath
     * @return array|mixed|null
     */
    public function getRequestOption($keyOrPath = null)
    {
        return $this->guzzleClient->getConfig($keyOrPath);
    }

    /**
     * Sets a request option for future requests
     * @param $keyOrPath
     * @param $value
     * @return $this
     */
    public function setRequestOption($keyOrPath, $value)
    {
        $this->requestOptions = array_merge(
            $this->requestOptions,
            [$keyOrPath => $value]
        );

        $handler = $this->guzzleClient->getConfig('handler');
        $this->guzzleClient = $this->createGuzzleClient($handler, $this->requestOptions);

        return $this;
    }

}
