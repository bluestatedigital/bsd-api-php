<?php

namespace Blue\Tools\Api;

use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\modify_request;
use function GuzzleHttp\Psr7\parse_query;
use Psr\Http\Message\RequestInterface;

class SigningHandler
{
    /** @var callable */
    private $nextHandler;


    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    function __invoke(RequestInterface $request, $options = [])
    {
        $uri = $request->getUri();

        /** @var array $query */
        $query = parse_query($request->getUri()->getQuery());



        // validate auth config
        $auth = $options['auth'];
        if ((!is_array($auth)) || (count($auth) < 3) || ($auth[2] != Client::$AUTH_TYPE)) {
            throw new \RuntimeException("Authorization information not provided");
        }
        $id = $auth[0];
        $secret = $auth[1];

        /*
         * Add id and version to the query
         */
        $query['api_id'] = $id;
        $query['api_ver'] = Client::$VERSION;

        /*
         * Add timestamp to the query
         */
        if (!isset($query['api_ts'])) {
            $query['api_ts'] = time();
        }

        $mac = $this->generateMac($uri->getPath(), build_query($query, false), $secret);

        $query['api_mac'] = $mac;

        $fn = $this->nextHandler;
        return $fn( modify_request($request, ['query' => build_query($query)]), $options );
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
        $queryParts = parse_query($query, false);

        // break URL into parts to get the path
        $urlParts = parse_url($url);
        // trim double slashes in the path
        if (substr($urlParts['path'], 0, 2) == '//') {
            $urlParts['path'] = substr($urlParts['path'], 1);
        }

        // combine strings to build the signing string
        $signingString = $queryParts['api_id'] . "\n" .
            $queryParts['api_ts'] . "\n" .
            $urlParts['path'] . "\n" .
            build_query($queryParts, false);
        $mac = hash_hmac('sha1', $signingString, $secret);
        return $mac;
    }
}