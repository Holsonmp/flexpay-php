<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Support;

use RuntimeException;

final class AssetPublisher
{
    /** @var list<string> */
    private const ASSETS = [
        'flexpay-checkout.esm.js',
        'flexpay-checkout.iife.js',
        'flexpay-checkout.css',
    ];

    public function __construct(private readonly ?string $distributionDirectory = null)
    {
    }

    /** @return list<string> */
    public function publish(string $targetDirectory, bool $overwrite = false): array
    {
        $targetDirectory = rtrim(trim($targetDirectory), "\\/");
        if ($targetDirectory === '') {
            throw new RuntimeException('Publication des assets : le dossier cible est obligatoire.');
        }
        $this->assertNoSymlinkComponents($targetDirectory);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Publication des assets : impossible de créer le dossier cible.');
        }
        if (is_link($targetDirectory)) {
            throw new RuntimeException('Publication des assets : un dossier cible symbolique est refusé.');
        }
        $resolvedTargetDirectory = realpath($targetDirectory);
        if ($resolvedTargetDirectory === false || !is_dir($resolvedTargetDirectory)) {
            throw new RuntimeException('Publication des assets : le dossier cible est introuvable.');
        }

        $sourceDirectory = $this->distributionDirectory ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'dist';
        $resolvedSourceDirectory = realpath($sourceDirectory);
        if ($resolvedSourceDirectory === false || !is_dir($resolvedSourceDirectory)) {
            throw new RuntimeException('Publication des assets : le dossier distribué est introuvable.');
        }
        $published = [];
        foreach (self::ASSETS as $asset) {
            $source = $resolvedSourceDirectory . DIRECTORY_SEPARATOR . $asset;
            $resolvedSource = realpath($source);
            $target = $resolvedTargetDirectory . DIRECTORY_SEPARATOR . $asset;
            if (
                $resolvedSource === false
                || dirname($resolvedSource) !== $resolvedSourceDirectory
                || is_link($source)
                || !is_file($resolvedSource)
            ) {
                throw new RuntimeException('Publication des assets : fichier distribué manquant : ' . $asset);
            }
            $targetKind = $this->targetKind($target);
            $targetExists = $targetKind !== 'missing';
            if (in_array($targetKind, ['link', 'other'], true)) {
                throw new RuntimeException('Publication des assets : destination non régulière refusée : ' . $asset);
            }
            if ($targetExists && !$overwrite) {
                throw new RuntimeException(
                    'Publication des assets : ' . $asset . ' existe déjà. Utilisez --force pour l’écraser.'
                );
            }

            $temporary = tempnam($resolvedTargetDirectory, '.flexpay-asset-');
            if ($temporary === false) {
                throw new RuntimeException('Publication des assets : fichier temporaire impossible : ' . $asset);
            }
            if (!copy($resolvedSource, $temporary) || !chmod($temporary, 0644)) {
                @unlink($temporary);
                throw new RuntimeException('Publication des assets : copie impossible : ' . $asset);
            }

            $targetKind = $this->targetKind($target);
            if (in_array($targetKind, ['link', 'other'], true)) {
                @unlink($temporary);
                throw new RuntimeException(
                    'Publication des assets : destination modifiée pendant la copie : ' . $asset
                );
            }
            if (!$overwrite && $targetKind !== 'missing') {
                @unlink($temporary);
                throw new RuntimeException('Publication des assets : destination créée pendant la copie : ' . $asset);
            }
            if (!rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('Publication des assets : remplacement atomique impossible : ' . $asset);
            }
            $published[] = $target;
        }

        return $published;
    }

    private function assertNoSymlinkComponents(string $path): void
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : (string) getcwd() . DIRECTORY_SEPARATOR . $path;
        $current = DIRECTORY_SEPARATOR;
        $depth = 0;
        foreach (explode(DIRECTORY_SEPARATOR, trim($absolute, DIRECTORY_SEPARATOR)) as $component) {
            if ($component === '') {
                continue;
            }
            ++$depth;
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $component;
            // Le premier composant peut être un alias système (notamment /var sur macOS).
            if ($depth > 1 && (file_exists($current) || is_link($current)) && is_link($current)) {
                throw new RuntimeException('Publication des assets : un composant symbolique est refusé.');
            }
        }
    }

    private function targetKind(string $path): string
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            return 'link';
        }
        if (!file_exists($path)) {
            return 'missing';
        }

        return is_file($path) ? 'regular' : 'other';
    }
}
