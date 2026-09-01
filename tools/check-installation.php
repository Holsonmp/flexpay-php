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
$temporary = sys_get_temp_dir() . '/flexpay-consumer-' . bin2hex(random_bytes(8));
$assets = $temporary . '/public/assets';

/** @param list<string> $command */
function runInstallationCommand(array $command, string $workingDirectory): void
{
    $process = proc_open(
        $command,
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Installation vierge : impossible de lancer ' . $command[0] . '.');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(
            'Installation vierge : commande en échec (' . $exitCode . ") :\n" . trim($output)
        );
    }
}

function removeInstallationDirectory(string $directory, string $expectedPrefix): void
{
    if (!str_starts_with($directory, $expectedPrefix) || !is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        if ($entry->isLink() || $entry->isFile()) {
            unlink($entry->getPathname());
        } else {
            rmdir($entry->getPathname());
        }
    }
    rmdir($directory);
}

try {
    if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
        throw new RuntimeException('Installation vierge : dossier temporaire impossible.');
    }
    $composerConfiguration = [
        'name' => 'holduix/flexpay-consumer-smoke',
        'type' => 'project',
        'repositories' => [[
            'type' => 'path',
            'url' => $root,
            'options' => ['symlink' => false],
        ]],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
    ];
    file_put_contents(
        $temporary . '/composer.json',
        json_encode($composerConfiguration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
    runInstallationCommand(
        [
            'composer',
            'require',
            'holduix/flexpay-php:@dev',
            'nyholm/psr7:^1.8',
            '--no-interaction',
            '--prefer-dist',
            '--no-progress',
        ],
        $temporary
    );

    $consumer = <<<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Holduix\FlexPay\Checkout\Support\AssetPublisher;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$http = new class implements ClientInterface {
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $payload = json_decode((string) $request->getBody(), true, 32, JSON_THROW_ON_ERROR);
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'pending',
            'reference' => $payload['reference'],
            'orderNumber' => 'FAKE-CONSUMER-MM',
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
        ], JSON_THROW_ON_ERROR));
    }
};
$cardAdapter = new class implements HostedCardCheckoutInterface {
    public function createCheckout(CardPaymentRequest $request, string $normalizedPhone): CardPaymentResult
    {
        return new CardPaymentResult(
            true,
            'https://cardpayment.flexpay.cd/fake?session=consumer',
            NormalizedPaymentStatus::REQUIRES_ACTION,
            'requires_action',
            ['fixture' => true],
            $request->merchantReference,
            null,
            $request->paymentMethod,
            $request->amount,
            strtoupper($request->currency)
        );
    }
};
$factory = new Psr17Factory();
$client = new FlexPayClient(
    new FlexPayConfiguration(
        'https://backend.flexpay.cd',
        'https://cardpayment.flexpay.cd',
        'FAKE-MERCHANT',
        'fake-token',
        'fake-callback-token'
    ),
    $http,
    $factory,
    $factory,
    hostedCardCheckout: $cardAdapter
);
$mobile = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
    50,
    'USD',
    '+243970000003',
    'CONSUMER-MM',
    'https://example.test/callback'
));
if (!$mobile->accepted) {
    throw new RuntimeException('Mobile Money fake non accepté.');
}
$card = $client->card()->createPayment(new CardPaymentRequest(
    50,
    'USD',
    'CONSUMER-CARD',
    'Test carte hébergée',
    'https://example.test/callback',
    'https://example.test/success',
    'https://example.test/cancel',
    'https://example.test/decline',
    'https://example.test/',
    paymentMethod: PaymentMethod::CARD
));
if ($card->normalizedStatus !== NormalizedPaymentStatus::REQUIRES_ACTION) {
    throw new RuntimeException('Carte fake non initialisée.');
}
$event = $client->webhooks()->parse(
    ['X-FlexPay-Callback-Token' => 'fake-callback-token'],
    '{"merchant_reference":"CONSUMER-MM","orderNumber":"FAKE-CONSUMER-MM","status":"pending"}'
);
if ($event->merchantReference !== 'CONSUMER-MM') {
    throw new RuntimeException('Callback fake invalide.');
}
$published = (new AssetPublisher())->publish($argv[1]);
if (count($published) !== 3) {
    throw new RuntimeException('Assets incomplets.');
}
PHP;
    file_put_contents($temporary . '/consume.php', $consumer);
    runInstallationCommand([PHP_BINARY, $temporary . '/consume.php', $assets], $temporary);

    fwrite(STDOUT, "Installation vierge : require path, autoload, fakes et assets validés.\n");
} finally {
    removeInstallationDirectory($temporary, sys_get_temp_dir() . '/flexpay-consumer-');
}
