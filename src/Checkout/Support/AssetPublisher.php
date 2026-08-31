<?php

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
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Publication des assets : impossible de créer le dossier cible.');
        }

        $sourceDirectory = $this->distributionDirectory ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'dist';
        $published = [];
        foreach (self::ASSETS as $asset) {
            $source = $sourceDirectory . DIRECTORY_SEPARATOR . $asset;
            $target = $targetDirectory . DIRECTORY_SEPARATOR . $asset;
            if (!is_file($source)) {
                throw new RuntimeException('Publication des assets : fichier distribué manquant : ' . $asset);
            }
            if (file_exists($target) && !$overwrite) {
                throw new RuntimeException(
                    'Publication des assets : ' . $asset . ' existe déjà. Utilisez --force pour l’écraser.'
                );
            }
            if (!copy($source, $target)) {
                throw new RuntimeException('Publication des assets : copie impossible : ' . $asset);
            }
            $published[] = $target;
        }

        return $published;
    }
}
