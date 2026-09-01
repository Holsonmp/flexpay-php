<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$assets = [
    'dist/flexpay-checkout.esm.js',
    'dist/flexpay-checkout.iife.js',
    'dist/flexpay-checkout.css',
];
$before = [];
foreach ($assets as $asset) {
    $hash = is_file($root . '/' . $asset) ? hash_file('sha256', $root . '/' . $asset) : false;
    $before[$asset] = is_string($hash) ? $hash : '';
}

$process = proc_open(
    ['npm', 'run', 'build'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root
);
if (!is_resource($process)) {
    fwrite(STDERR, "Assets : impossible de lancer le build.\n");
    exit(1);
}
$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    fwrite(STDERR, "Assets : build en échec.\n" . trim($output) . "\n");
    exit(1);
}

$changed = [];
foreach ($assets as $asset) {
    $hash = is_file($root . '/' . $asset) ? hash_file('sha256', $root . '/' . $asset) : false;
    $after = is_string($hash) ? $hash : '';
    if ($before[$asset] === '' || !hash_equals($before[$asset], $after)) {
        $changed[] = $asset;
    }
}
if ($changed !== []) {
    fwrite(STDERR, "Assets : dist n’était pas synchronisé avec les sources :\n- " . implode("\n- ", $changed) . "\n");
    exit(1);
}

fwrite(STDOUT, "Assets : build reproductible et dist synchronisé.\n");
