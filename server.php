<?php

use App\Support\LocalPublicFile;

header_remove('X-Powered-By');

$publicPath = __DIR__.'/public';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
require_once __DIR__.'/app/Support/LocalPublicFile.php';

if (LocalPublicFile::mayServe($uri, $publicPath, __DIR__.'/storage/app/public')) {
    return false;
}

$deniedFile = realpath($publicPath.$uri);
if ($deniedFile !== false && is_file($deniedFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';

    return true;
}

require_once $publicPath.'/index.php';
