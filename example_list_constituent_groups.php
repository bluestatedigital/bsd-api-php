<?php
/**
 * Copyright 2013 Blue State Digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * This example will return a list of all of the constituent groups in your BSD
 * Tools. Fill in your API ID, API secret, and the base URL of your BSD install
 * (including /page/api/ at the end), and you should be able to execute it out
 * of the box.
 *
 * autoload.php provides one way of loading the API library and supporting
 * files, all packaged in lib/. You can also use another autoloader (any of the
 * PSR-0 autoloaders should work). If you use another autoloading method, you
 * may need to move the files in lib/ to your include path, or add the directory
 * to your include path.
 */

// show all warnings and errors in examples - don't hide anything :)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$api_id = ''; // your API ID
$api_secret = ''; // your API Secret
$api_url = ''; // your BSD URL - e.g. https://client.cp.bsd.net

require 'autoload.php';

$api = new BlueStateDigital_Api($api_id, $api_secret, $api_url);
$res = $api->get('cons_group/list_constituent_groups');
echo $res->getBody();
