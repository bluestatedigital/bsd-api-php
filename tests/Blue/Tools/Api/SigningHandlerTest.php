<?php

namespace Blue\Tools\Api;

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
    public function testMacSignature()
    {
        $request = new Request(
            'GET',
            (new Uri('http://bsd.net/my-api-path'))->withQuery('api_ts=54321')
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
        $this->assertEquals(54321, $query['api_ts']);
        $this->assertEquals(Client::$VERSION, $query['api_ver']);
        $this->assertEquals('377d739e406238ba2e03bec985b882ee4c0c2b05', $query['api_mac']);
    }
}
