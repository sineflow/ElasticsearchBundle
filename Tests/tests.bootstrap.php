<?php

// Delete cache dir
$filesystem = new \Symfony\Component\Filesystem\Filesystem();
$cacheDir = __DIR__ . '/app/cache/test';
if ($filesystem->exists($cacheDir)) {
    $filesystem->remove($cacheDir);
}

require __DIR__ . '/../vendor/autoload.php';
