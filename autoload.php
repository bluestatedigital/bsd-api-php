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
 * autoload.php provides one way of loading the API library and supporting
 * files, all packaged in lib/. You can also use another autoloader (any of the
 * PSR-0 autoloaders should work). If you use another autoloading method, you
 * may need to move the files in lib/ to your include path, or add the directory
 * to your include path.
 */

spl_autoload_register(
    function($class)
    {
        include __DIR__ . '/lib/' . str_replace('_', '/', $class) . '.php';
    }
);