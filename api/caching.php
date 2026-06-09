<?php

function cachedGet(string $url, int $ttlSeconds = 86400): string
{
    $cacheDir = __DIR__ . '/cache';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }

    $cacheFile = $cacheDir . '/' . sha1($url) . '.json';

    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $ttlSeconds) {
        return file_get_contents($cacheFile);
    }

    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException('Upstream request failed');
    }

    file_put_contents($cacheFile, $response);

    return $response;
}