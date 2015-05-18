Blue State Digital PHP API Client
=================================

This library provides an interface to the BSD Tools.

Example usage:

```
use Blue\Tools\Api\Client;

$client = new Client('user_id', 'secret', 'https://baseurl.com');

/** @var ResponseInterface $response */
$response = $client->get('api/list_things', ['param' => 'value']);
```

The BSD Tools API will sometimes return a deferred result, which means that the results of the call are not immediately available. The above call, however, will poll for updates to this API call, and will block until the result has been resolved. The manner in which the client polls for updates can be configured as follows:

```
// Set the client to wait 30 seconds between updates
$client->setDeferredResultInterval(30);

// Set the client to give up after 10 updates (a RuntimeException will be thrown)
$client->setDeferredResultMaxAttempts(10);
```

Installation
------------

Update `composer.json`:
```
"require": {
    "bluestatedigital/tools-api-client": "~2.0"
}
```