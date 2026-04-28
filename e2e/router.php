<?php

/**
 * Router pour le serveur PHP intégré utilisé par Playwright (test_e2e).
 * Sert les fichiers statiques tels quels et délègue le reste à public/index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';
