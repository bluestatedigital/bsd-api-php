<?php
namespace Blue\Tools\Api;

use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

final class SigningHandler
{
    /** @var callable */
    private $nextHandler;

    /**
     * Constructs the signing middleware
     *
     * @param callable $nextHandler
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * Signs the API request
     *
     * @param  \Psr\Http\Message\RequestInterface $request
     * @param  array $options
     * @return mixed
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;

        // validate auth config
        $auth = $options['auth'];
        if ((!is_array($auth)) || (count($auth) < 3) || ($auth[2] != Client::$AUTH_TYPE)) {
            throw new \RuntimeException("Authorization information not provided");
        }

        $id = $auth[0];
        $secret = $auth[1];

        // add required parameters to query
        $query = Psr7\parse_query($request->getUri()->getQuery());

        $query['api_ts'] = isset($query['api_ts']) ? $query['api_ts'] : time();
        $query['api_ver'] = 2;
        $query['api_id'] = $id;
        $query['api_mac'] = $this->generateMac($request->getUri(), $query, $secret);

        $query = Psr7\build_query($query);

        // apply the next handler
        return $fn(Psr7\modify_request($request, ['query' => $query]), $options);
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
}