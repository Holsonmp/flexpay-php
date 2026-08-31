<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicDirectory = realpath(__DIR__ . '/public');
$requestedFile = realpath(__DIR__ . '/public' . $path);

// Le serveur PHP intégré doit laisser les ressources statiques au serveur web.
if (
    $publicDirectory !== false
    && $requestedFile !== false
    && str_starts_with($requestedFile, $publicDirectory . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

require __DIR__ . '/public/index.php';
