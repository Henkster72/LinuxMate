<?php
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[a-z0-9-]+$/', $id)) {
    http_response_code(404);
    exit;
}

$packagesFile = __DIR__ . '/../data/packages.json';
$packages = json_decode(file_get_contents($packagesFile), true) ?? [];

foreach ($packages as $pkg) {
    if (($pkg['id'] ?? '') !== $id) {
        continue;
    }

    $icon = $pkg['icon_svg'] ?? '';
    if (!is_string($icon) || stripos(ltrim($icon), '<svg') !== 0) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=31536000, immutable');
    echo $icon;
    exit;
}

http_response_code(404);
