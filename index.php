<?php

require './vendor/autoload.php';

use ElementaryFramework\WaterPipe\WaterPipe;
use ElementaryFramework\WaterPipe\HTTP\Request\Request;
use ElementaryFramework\WaterPipe\HTTP\Response\Response;
use ElementaryFramework\WaterPipe\HTTP\Response\ResponseStatus;

const BadRequestMessage = '400 Error: Bad Request.';
const NotFoundMessage = '404 Error: Not Found.';
const StorageUri = '/storage/(.+)';
const FileMimeType = 'application/octet-stream';
const DirectoryMode = 0755;

function getDirectoryFiles($dir)
{
    $result = array();
    $scannedDir = scandir($dir);
    $scannedDir = array_diff($scannedDir, array('..', '.'));
    foreach ($scannedDir as $key => $value) {
        if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
            $result[$value] = getDirectoryFiles($dir . DIRECTORY_SEPARATOR . $value);
        } else {
            $result[] = $value;
        }
    }
    return $result;
}

function removeDirectory($dir)
{
    $includes = new FilesystemIterator($dir);
    foreach ($includes as $include) {
        if (is_dir($include) && !is_link($include)) {
            removeDirectory($include);
        } else {
            unlink($include);
        }
    }
    rmdir($dir);
}

$pipe = new WaterPipe;
$requestUri = $_SERVER['REQUEST_URI'];
Request::capture()->uri->setUri($requestUri);
$path = rtrim(__DIR__ . $requestUri, '/');

$pipe->get(StorageUri, function (Request $req, Response $res) use ($path) {
    if (is_file($path)) {
        $res->sendFile($path, ResponseStatus::OkCode, FileMimeType);
    } elseif (is_dir($path)) {
        $res->sendJson(getDirectoryFiles($path), ResponseStatus::OkCode);
    } else {
        $res->sendText(NotFoundMessage, ResponseStatus::NotFoundCode);
    }
});

$pipe->post(StorageUri, function (Request $req, Response $res) use ($path) {
    $copyPath = $req->getHeader()->getField('X-Copy-From');
    if ($copyPath) {
        $sourcePath = __DIR__ . $copyPath;
        $pathDir = dirname($path);
        if (!file_exists($pathDir)) {
            mkdir($pathDir, DirectoryMode, true);
        }
        if (copy($sourcePath, $path)) {
            $res->sendText('Copied.', ResponseStatus::OkCode);
        } else {
            $res->sendText(NotFoundMessage, ResponseStatus::NotFoundCode);
        }
    } else {
        $res->sendText(BadRequestMessage, ResponseStatus::BadRequestCode);
    }
});

$pipe->put(StorageUri, function (Request $req, Response $res) use ($path) {
    if ($putData = $req->getBody()) {
        $pathDir = dirname($path);
        if (!file_exists($pathDir)) {
            mkdir($pathDir, DirectoryMode, true);
        }
        file_put_contents($path, $putData);
        $res->sendText('Successfully Uploaded.', ResponseStatus::OkCode);
    } else {
        $res->sendText(BadRequestMessage, ResponseStatus::BadRequestCode);
    }
});

$pipe->head(StorageUri, function (Request $req, Response $res) use ($path) {
    if (is_file($path)) {
        $res->getHeader()->setField('Filename', basename($path));
        $res->getHeader()->setField('Filesize', filesize($path));
        $res->getHeader()->setField('Last-Change-Time', date('d.m.Y H:i:s', filemtime($path)));
        $res->sendText('', ResponseStatus::OkCode);
    } else {
        $res->sendText('', ResponseStatus::NotFoundCode);
    }
});

$pipe->delete(StorageUri, function (Request $req, Response $res) use ($path) {
    if (is_file($path)) {
        unlink($path);
        $res->sendText('File Deleted.', ResponseStatus::OkCode);
    } elseif (is_dir($path)) {
        removeDirectory($path);
        $res->sendText('Directory Deleted.', ResponseStatus::OkCode);
    } else {
        $res->sendText(NotFoundMessage, ResponseStatus::NotFoundCode);
    }
});

$pipe->error(ResponseStatus::NotFoundCode, function (Request $req, Response $res) {
    $res->sendText(NotFoundMessage, ResponseStatus::NotFoundCode);
});

$pipe->run();
