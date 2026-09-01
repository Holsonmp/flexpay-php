<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$failures = [];
$checked = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/examples', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo) {
        continue;
    }
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    ++$checked;
    $command = [PHP_BINARY, '-l', $file->getPathname()];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        $failures[] = $file->getPathname() . ' (lint impossible)';
        continue;
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        $failures[] = $file->getPathname() . ': ' . trim($output);
        continue;
    }

    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) {
        $failures[] = $file->getPathname() . ' (illisible)';
        continue;
    }
    preg_match_all('/^use\s+(Holduix\\\\FlexPay\\\\[^;\s]+)(?:\s+as\s+[^;]+)?;/m', $source, $imports);
    foreach ($imports[1] as $import) {
        if (!class_exists($import) && !interface_exists($import) && !enum_exists($import)) {
            $failures[] = $file->getPathname() . ': import inexistant ' . $import;
        }
    }
}

$smoke = proc_open(
    [PHP_BINARY, $root . '/examples/plain-php/smoke.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root
);
if (!is_resource($smoke)) {
    $failures[] = 'examples/plain-php/smoke.php (exécution impossible)';
} else {
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($smoke) !== 0) {
        $failures[] = 'examples/plain-php/smoke.php: ' . trim($output);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Exemples invalides :\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Exemples : %d fichiers PHP valides et smoke fake réussi.\n", $checked));
