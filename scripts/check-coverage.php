<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

$coverageFile = $argv[1] ?? 'coverage.xml';
$minimum = isset($argv[2]) ? (float) $argv[2] : 85.0;

if (!is_file($coverageFile)) {
    fwrite(STDERR, "Couverture : fichier Clover introuvable.\n");
    exit(2);
}

$xml = simplexml_load_file($coverageFile);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, "Couverture : rapport Clover invalide.\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $statements > 0 ? ($covered / $statements) * 100 : 0.0;

fwrite(STDOUT, sprintf(
    "Couverture des instructions : %.2f%% (%d/%d), minimum %.2f%%.\n",
    $percentage,
    $covered,
    $statements,
    $minimum
));

exit($percentage + 0.00001 >= $minimum ? 0 : 1);
