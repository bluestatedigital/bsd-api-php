<?php
use Blue\Tools\Api\Client;

class ExampleTest extends PHPUnit_Framework_TestCase
{

    /**
     * To use this test, set the first three environment variables to match your own credentials. The test should
     * output data for the first constituent in your database.
     *
     * This test is ignored by the test suite.
     */
    public function testApiCall()
    {
        $id = getenv('BSD_API_ID');
        $secret = getenv('BSD_API_SECRET');
        $baseUrl = getenv('BSD_API_BASEURL');

        if ($id && $secret && $baseUrl) {

            $client = new Client(
                $id, $secret, $baseUrl
            );

            $response = $client->get(
                "cons/get_constituents_by_id",
                [
                    'cons_ids' => '1'
                ]
            );

            $contents = $response->getBody()->getContents();

            echo $contents;
        }
    }
}