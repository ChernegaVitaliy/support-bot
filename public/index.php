<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// When running with the built-in PHP server (`php -S`), the router script
// receives every request. Serve existing static files from public/ directly
// with the correct content type; let Symfony handle everything else.
if (PHP_SAPI === 'cli-server') {
    $requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $file = __DIR__ . $requestPath;

    if ($requestPath !== '/' && is_file($file)) {
        $realFile = realpath($file);
        $realPublic = realpath(__DIR__);

        if ($realFile !== false && $realPublic !== false
            && strpos($realFile, $realPublic . DIRECTORY_SEPARATOR) === 0) {
            $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
            $mimes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
            ];
            $mime = $mimes[$ext] ?? (mime_content_type($realFile) ?: 'application/octet-stream');
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($realFile));
            readfile($realFile);
            return;
        }
    }
}

$kernel = new Kernel('prod', false);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
