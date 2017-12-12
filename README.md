Blue State Digital PHP API Client
=================================

[![Build Status](https://travis-ci.org/bluestatedigital/bsd-api-php.svg?branch=master)](https://travis-ci.org/bluestatedigital/bsd-api-php)

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

The client polling for deferred calls can also be disabled.  When disabled, the deferred ID will be returned:

```
// Disable polling for the results and return the deferred ID
$client->setProcessDeferredResults(false);
```

Installation
------------

Update `composer.json`:
```
"require": {
    "bluestatedigital/tools-api-client": "~3.0"
}
```

Handling Responses
------------------

By default the API client uses Guzzle's RequestException for any responses above HTTP 300. To use your own handler you may either create your own Guzzle error plugin or emitter (see Guzzle 5 documentation), or disable Guzzle's handler entirely by using the following code:
```
// Prevent Exceptions on non-actionable HTTP response (400 and 500 range)
$client->setRequestOption('exceptions', false);
```
