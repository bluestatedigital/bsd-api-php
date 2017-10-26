<?php

namespace Blue\Tools\Api;

use Carbon\Carbon;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

class SigningHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @scenario Handler should ensure proper auth options have been supplied
     *
     * @dataProvider invalidAuthOptions
     * @expectedException \RuntimeException
     *
     * @param $authOptions
     */
    public function testValidAuthOptions($authOptions)
    {
        $handler = new SigningHandler(
            function(RequestInterface $request) {
                return $request;
            }
        );

        $handler(new Request('GET', '/some-path'), ['auth' => $authOptions]);
    }

    /**
     * @return array
     */
    public function invalidAuthOptions()
    {
        return [
            // Not an array:
            ['some string'],
            [false],

            // Not enough options:
            [[1, 2]],

            // Wrong auth type:
            [[1, 2, 'not-the-correct-auth-type']]
        ];
    }

    /**
     * @scenario Handler should properly append an api_mac parameter
     */
    public function testMacSignatureWithoutTimestamp()
    {
        $now = Carbon::createFromTimestamp(55555);
        Carbon::setTestNow($now);

        $request = new Request(
            'GET',
            (new Uri('http://bsd.net/my-api-path'))
        );

        $handler = new SigningHandler(
            function(RequestInterface $request) {
                return $request;
            }
        );

        /** @var RequestInterface $modifiedRequest */
        $modifiedRequest = $handler($request, ['auth' => [123, 'a1b2c3', Client::$AUTH_TYPE]]);

        $query = parse_query($modifiedRequest->getUri()->getQuery());

        $this->assertArrayHasKey('api_id', $query);
        $this->assertArrayHasKey('api_ver', $query);
        $this->assertArrayHasKey('api_ts', $query);
        $this->assertArrayHasKey('api_mac', $query);

        $this->assertEquals(123, $query['api_id']);
        $this->assertEquals(55555, $query['api_ts']);
        $this->assertEquals(Client::$VERSION, $query['api_ver']);
        $this->assertEquals('1d16e325742f7d4c77801513c0cf7ba3208c7d97', $query['api_mac']);
    }

    /**
     * @scenario Handler should properly append an api_mac parameter when an api_ts parameter is included
     */
    public function testMacSignatureWithTimestamp()
    {
        $request = new Request(
            'GET',
            (new Uri('http://bsd.net/my-api-path'))->withQuery(
                build_query(['api_ts' => 77777])
            )
        );

        $handler = new SigningHandler(
            function(RequestInterface $request) {
                return $request;
            }
        );

        /** @var RequestInterface $modifiedRequest */
        $modifiedRequest = $handler($request, ['auth' => [123, 'a1b2c3', Client::$AUTH_TYPE]]);

        $query = parse_query($modifiedRequest->getUri()->getQuery());

        $this->assertArrayHasKey('api_ts', $query);
        $this->assertArrayHasKey('api_mac', $query);

        $this->assertEquals(77777, $query['api_ts']);
        $this->assertEquals('3a0c45dd4ce055eebb940b0b5874cd6312f62d81', $query['api_mac']);
    }
}
