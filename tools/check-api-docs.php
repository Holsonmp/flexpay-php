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
$documentation = file_get_contents($root . '/docs/api-reference.md');
if (!is_string($documentation)) {
    fwrite(STDERR, "API publique : docs/api-reference.md est absent.\n");
    exit(1);
}

$missing = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo) {
        continue;
    }
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) {
        $missing[] = $file->getPathname() . ' (illisible)';
        continue;
    }
    if (preg_match('/namespace\s+([^;]+);/', $source, $namespace) !== 1) {
        continue;
    }
    $symbolPattern = '/(?:final\s+|abstract\s+)?(?:class|interface|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/';
    if (preg_match($symbolPattern, $source, $symbol) !== 1) {
        continue;
    }
    $qualifiedName = trim($namespace[1]) . '\\' . $symbol[1];
    if (!str_contains($documentation, '`' . $qualifiedName . '`')) {
        $missing[] = $qualifiedName;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "API publique : symboles absents de la référence :\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

fwrite(STDOUT, "API publique : tous les symboles PHP sont cartographiés.\n");
