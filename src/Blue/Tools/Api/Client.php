<?php
namespace Blue\Tools\Api;

use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use GuzzleHttp\Client as GuzzleClient;

class Client {

    /** @var int */
    static $VERSION = 2;

    /** @var string */
    static $AUTH_TYPE = 'bsdtools_v2';


    /** @var string */
    private $id;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $secret;

    /** @var int */
    private $deferredResultMaxAttempts = 20;

    /** @var int */
    private $deferredResultInterval = 5;

    /** @var LoggerInterface */
    private $logger;

    /** @var \GuzzleHttp\Client */
    private $guzzleClient;


    public function __construct($id, $secret, $url) {

        $this->logger = new NullLogger();
        $this->guzzleClient = new GuzzleClient(
            [
                'message_factory' => new MessageFactory()
            ]
        );

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
    }

    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Execute a GET request against the API
     *
     * @param string $apiPath
     * @param array $queryParams
     * @return ResponseInterface
     */
    public function get($apiPath, $queryParams = []) {

        $response = $this->guzzleClient->get(
            $this->baseUrl . $apiPath,
            [
                'query' => $queryParams,
                'future' => false,
                'auth' => [
                    $this->id,
                    $this->secret,
                    self::$AUTH_TYPE
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
    public function post($apiPath, $queryParams = [], $data = '') {

        $response = $this->guzzleClient->post(
            $this->baseUrl . $apiPath,
            [
                'query' => $queryParams,
                'body' => $data,
                'future' => false,
                'auth' => [
                    $this->id,
                    $this->secret,
                    self::$AUTH_TYPE
                ],
            ]
        );

        return $this->resolve($response);
    }


    /**
     * @param ResponseInterface $response
     * @return FutureResponse|Response|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|null
     */
    private function resolve(ResponseInterface $response) {

        if ($response->getStatusCode() == 202) {

            $key = $response->getBody()->getContents();

            $attempts = $this->deferredResultMaxAttempts;

            while($attempts > 0) {
                $deferredResponse = $this->guzzleClient->get(
                    $this->baseUrl . "get_deferred_results",
                    [
                        'auth' => [
                            $this->id,
                            $this->secret,
                            self::$AUTH_TYPE
                        ],
                        'future' => false,
                        'query' => [
                            'deferred_id' => $key
                        ]
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



}