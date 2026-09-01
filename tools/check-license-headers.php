<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

const FLEXPAY_PHP_HEADER = <<<'HEADER'
/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */
HEADER;

const FLEXPAY_ASSET_HEADER = <<<'HEADER'
/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */
HEADER;

const FLEXPAY_DIST_HEADER =
    '/*! FlexPay Intégration By Holsonmp - Holduix | Copyright (c) 2026 Holson Mpangala / Holduix | MIT */';

$root = dirname(__DIR__);
$fix = in_array('--fix', array_slice($argv, 1), true);
/** @var list<string> $files */
$files = [];

foreach (['src', 'config', 'bin', 'examples', 'resources', 'scripts', 'tools'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if (!$file->isFile()) {
            continue;
        }
        $extension = strtolower($file->getExtension());
        if (in_array($extension, ['php', 'js', 'mjs', 'css'], true) || $file->getPath() === $root . '/bin') {
            $files[] = $file->getPathname();
        }
    }
}
foreach (glob($root . '/dist/*.{js,css}', GLOB_BRACE) ?: [] as $file) {
    $files[] = $file;
}

/** @var list<string> $failures */
$failures = [];
foreach (array_values(array_unique($files)) as $file) {
    $content = file_get_contents($file);
    if (!is_string($content)) {
        $failures[] = $file . ' (illisible)';
        continue;
    }

    $relative = substr($file, strlen($root) + 1);
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $isPhp = $extension === 'php' || str_starts_with($relative, 'bin/');
    $expected = str_starts_with($relative, 'dist/')
        ? FLEXPAY_DIST_HEADER
        : ($isPhp ? FLEXPAY_PHP_HEADER : FLEXPAY_ASSET_HEADER);

    $prefix = substr($content, 0, 700);
    $hasExpectedHeader = str_contains($prefix, $expected)
        && (!$isPhp || str_contains($prefix, FLEXPAY_PHP_HEADER . "\n\ndeclare(strict_types=1);"));
    if (!$hasExpectedHeader) {
        if (!$fix) {
            $failures[] = $relative;
            continue;
        }

        if ($isPhp) {
            if (str_contains($content, FLEXPAY_PHP_HEADER)) {
                $content = str_replace(
                    FLEXPAY_PHP_HEADER . "\ndeclare(strict_types=1);",
                    FLEXPAY_PHP_HEADER . "\n\ndeclare(strict_types=1);",
                    $content
                );
                if (!str_contains(substr($content, 0, 700), FLEXPAY_PHP_HEADER . "\n\ndeclare(strict_types=1);")) {
                    $failures[] = $relative . ' (position de declare invalide)';
                    continue;
                }
            } else {
                $position = strpos($content, '<?php');
                if ($position === false) {
                    $content = "<?php\n\n" . FLEXPAY_PHP_HEADER
                        . "\n\ndeclare(strict_types=1);\n?>\n\n" . ltrim($content, "\r\n");
                } else {
                    $offset = $position + strlen('<?php');
                    $content = substr($content, 0, $offset)
                        . "\n\n" . FLEXPAY_PHP_HEADER . "\n\n"
                        . ltrim(substr($content, $offset), "\r\n");
                }
            }
        } else {
            $content = $expected . "\n\n" . ltrim($content, "\r\n");
        }

        if (file_put_contents($file, $content) === false) {
            $failures[] = $relative . ' (écriture impossible)';
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "En-têtes de licence absents ou invalides :\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("En-têtes de licence valides : %d fichier(s).\n", count(array_unique($files))));
