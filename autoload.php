<?php
/**
 * autoload.php provides one way of loading the API library and supporting
 * files, all packaged in lib/. You can also use another autoloader (any of the
 * PSR-0 autoloaders should work). If you use another autoloading method, you
 * may need to move the files in lib/ to your include path, or add the directory
 * to your include path.
 */

function __autoload($class)
{
    include __DIR__ . '/lib/' . str_replace('_', '/', $class) . '.php';
}
