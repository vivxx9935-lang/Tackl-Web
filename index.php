<?php
// GitHub Pages-friendly fallback: serve the static landing page when PHP is unavailable.
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    exit;
}

header('Location: php/index.php');
exit;
