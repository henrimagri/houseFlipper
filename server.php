<?php

$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($publicPath.$uri);

if ($uri !== '/' && $file !== false && str_starts_with($file, realpath($publicPath)) && is_file($file)) {
    return false;
}

require_once $publicPath.'/index.php';
