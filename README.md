# Holduix FlexPay PHP

Client PHP framework-agnostic extrait de l’intégration FlexPay du projet Étreinte. Il couvre uniquement les capacités prouvées : création C2B Mobile Money, checkout carte/portail hébergé, lecture de statut générique et carte, parsing de callback, statuts normalisés, erreurs typées et redaction.

Depuis la version 0.2, une couche optionnelle de checkout ajoute une session serveur, une machine à états, une persistance abstraite, un contrat HTTP, un contrôleur JavaScript headless et un modal accessible sans jQuery.

Le package ne collecte jamais de PAN ou CVV, ne connaît aucune commande et n’invente ni signature, ni remboursement, ni garantie d’idempotence. La persistance de checkout passe exclusivement par `CheckoutStoreInterface` ; le package n’accède directement à aucune base de données.

## Prérequis et installation

- PHP 8.1 à 8.4 ;
- client HTTP PSR-18 ;
- factories PSR-17 et messages PSR-7 ;
- logger PSR-3 optionnel.

```bash
composer require holduix/flexpay-php:^0.2
composer require guzzlehttp/guzzle nyholm/psr7
```

Dans le dépôt Étreinte, le package est installé comme repository Composer local de type `path`.

## Configuration et client

```php
use GuzzleHttp\Client as GuzzleClient;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

$configuration = new FlexPayConfiguration(
    apiBaseUrl: getenv('FLEXPAY_URL_API'),
    cardBaseUrl: getenv('FLEXPAY_URL_API_CARD'),
    merchantId: getenv('FLEXPAY_MERCHANT'),
    apiToken: getenv('FLEXPAY_TOKEN'),
    callbackToken: getenv('FLEXPAY_CALLBACK_TOKEN') ?: '',
    timeoutSeconds: 30
);
$factory = new Psr17Factory();
$client = new FlexPayClient(
    $configuration,
    new GuzzleClient(['timeout' => 30, 'http_errors' => false]),
    $factory,
    $factory
);
```

Le timeout PSR-18 doit être configuré sur l’implémentation HTTP. Le package classe ensuite les exceptions réseau et marque une création expirée comme ambiguë.

## Checkout complet

```php
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore;
use Holduix\FlexPay\Enum\PaymentMethod;

$checkout = new CheckoutManager($client, new InMemoryCheckoutStore());
$session = $checkout->create(new CreateCheckoutRequest(
    merchantReference: 'ORDER-2026-0001',
    amount: 50.00,
    currency: 'USD',
    allowedMethods: [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
    successUrl: 'https://example.com/payment/success',
    cancelUrl: 'https://example.com/payment/cancel',
    callbackUrl: 'https://example.com/payment/callback'
));
```

`InMemoryCheckoutStore` est réservé aux tests. Implémentez le contrat avec une contrainte unique, une transaction et un contrôle optimiste de `version`.

Initialisation et vérification :

```php
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;

$pending = $checkout->initialize(new InitializeCheckoutRequest(
    $session->id(),
    PaymentMethod::MOBILE_MONEY,
    '+243970000003'
));
$status = $checkout->refreshStatus(new RefreshCheckoutStatusRequest($session->id()));
```

Le callback est un signal : `handleCallback()` parse, corrèle et relit le fournisseur avant toute transition de succès. Les effets métier passent par `CheckoutEventListenerInterface` et restent idempotents dans l’application.

## Assets prêts à l’emploi

```bash
vendor/bin/flexpay-assets publish public/vendor/flexpay
```

La commande copie uniquement les trois fichiers de `dist/`. Elle refuse d’écraser sans `--force` et n’est jamais exécutée automatiquement par Composer.

```html
<link rel="stylesheet" href="/vendor/flexpay/flexpay-checkout.css">
<button type="button" id="pay-button" data-checkout-id="chk_opaque">Payer</button>
<script src="/vendor/flexpay/flexpay-checkout.iife.js" defer></script>
<script src="/assets/js/payment-page.js" defer></script>
```

Voir le [quickstart](docs/quickstart.md), le [contrat HTTP](docs/http-contract.md) et le [guide frontend](docs/frontend-checkout.md).

## Mobile Money

```php
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;

$result = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
    amount: 50.00,
    currency: 'USD',
    phoneNumber: '+243970000003',
    merchantReference: 'ORDER-2026-0001',
    callbackUrl: 'https://example.com/payment/flexpay/callback'
));

if ($result->accepted) {
    // Persister $result->providerTransactionId dans l’application.
}
```

## Carte Visa / checkout hébergé

```php
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;

$result = $client->card()->createPayment(new CardPaymentRequest(
    amount: 50.00,
    currency: 'USD',
    merchantReference: 'ORDER-2026-0002',
    description: 'Commande ORDER-2026-0002',
    callbackUrl: 'https://example.com/payment/flexpay/callback',
    approveUrl: 'https://example.com/payment/approve',
    cancelUrl: 'https://example.com/payment/cancel',
    declineUrl: 'https://example.com/payment/decline',
    homeUrl: 'https://example.com/'
));

header('Location: ' . $result->checkoutUrl, true, 302);
```

L’URL de checkout contient le token conformément au legacy : elle est sensible et ne doit pas être loggée. La réponse brute filtrée n’expose pas ce token.

## Statut

```php
$status = $client->transactions()->getStatus($providerTransactionId);
$cardStatus = $client->transactions()->getCardOrderStatus($providerTransactionId);

if ($status->normalizedStatus->value === 'successful') {
    // Vérifier référence, montant et devise locaux avant les effets métier.
}
```

Chaque résultat conserve `normalizedStatus`, `rawStatus`, `rawResponse`, références, méthode, montant, devise, message et date lorsqu’ils existent.

## Callback

```php
$event = $client->webhooks()->parse(
    headers: getallheaders(),
    body: file_get_contents('php://input'),
    query: $_GET
);
```

Le token callback applicatif observé est pris en charge. Aucune signature HMAC n’est vérifiée, car aucun format ni algorithme FlexPay n’est confirmé. L’application doit relire le statut serveur et traiter l’événement de façon idempotente.

## Erreurs et sécurité

Les erreurs distinguent configuration, validation, 401, 403, API, réseau, timeout, 429, réponse invalide et callback invalide. `TimeoutException::$ambiguous` vaut `true` pour une création : ne la relancez pas automatiquement.

Les logs PSR-3 ne contiennent jamais token, authorization, données carte ni téléphone complet. Sans logger fourni, `NullLogger` est utilisé.

## Tests

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
vendor/bin/phpunit
vendor/bin/phpstan analyse src tests
vendor/bin/phpcs --standard=PSR12 src tests
npm ci
npm test
npm run build
```

Node.js est uniquement nécessaire pour modifier et tester les sources frontend, jamais pour consommer `dist/`. Les tests sandbox sont désactivés par défaut et potentiellement transactionnels. Voir [testing.md](docs/testing.md), [migration-guide.md](docs/migration-guide.md) et [security.md](docs/security.md).
