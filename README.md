# FlexPay PHP

Bibliothèque PHP indépendante, sans dépendance à un framework, pour intégrer les flux FlexPay confirmés par le code et les fixtures historiques du projet.

> Ce package est une intégration indépendante. Il ne doit pas être présenté comme le SDK PHP officiel de FlexPay sans autorisation vérifiable du fournisseur.

## Statut

Le package est en préparation de pré-release. Mobile Money, lecture de statut, callbacks authentifiés, orchestration de checkout et interface optionnelle sont testés avec des fakes. Les endpoints, valeurs de statut et mécanismes d’authentification doivent encore être validés avec le contrat fournisseur avant production. Aucun test local n’effectue de paiement réel.

La carte hébergée est **désactivée par défaut**. Elle nécessite une implémentation de `HostedCardCheckoutInterface` fondée sur un contrat fournisseur confirmé et produisant une URL publique éphémère sans Bearer serveur.

## Fonctionnalités

- client PSR-18 avec requêtes PSR-7/PSR-17 et journalisation PSR-3 ;
- création Mobile Money, normalisation du téléphone et absence de retry automatique de création ;
- lecture prudente des statuts Mobile Money et carte ;
- DTO typés, erreurs typées et réponses brutes filtrées ;
- parsing JSON ou formulaire de callbacks avec token obligatoire par défaut ;
- checkout persistant, machine à états, contrôle optimiste, déduplication et vérification serveur ;
- contrôleur JavaScript headless et modal optionnel en ESM/IIFE/CSS ;
- exemples PHP natif, CodeIgniter 4 et Laravel.

Ne sont pas pris en charge : collecte de PAN/CVV, remboursement, annulation fournisseur, signature cryptographique de callback non documentée, garantie d’idempotence native du fournisseur et stockage de production prêt à l’emploi.

## Prérequis

- PHP 8.1 ou supérieur ;
- une implémentation PSR-18 ;
- des factories PSR-17 ;
- des identifiants et endpoints obtenus auprès du fournisseur pour un usage réel ;
- Node.js uniquement pour modifier ou reconstruire les assets frontend.

## Installation

Après publication sur Packagist :

```bash
composer require holduix/flexpay-php
```

Avant publication, utiliser un repository Composer VCS pointant vers le futur dépôt public, ou un repository `path` depuis un projet de test. La commande Packagist ci-dessus n’est donc pas encore annoncée comme disponible.

Une implémentation concrète peut être ajoutée dans l’application, par exemple :

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

## Configuration et client

Les secrets restent dans l’environnement de l’application ; le cœur du package ne lit pas `$_ENV`.

```php
use GuzzleHttp\Client as GuzzleClient;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;

$factory = new Psr17Factory();
$configuration = new FlexPayConfiguration(
    apiBaseUrl: (string) getenv('FLEXPAY_URL_API'),
    cardBaseUrl: (string) getenv('FLEXPAY_URL_API_CARD'),
    merchantId: (string) getenv('FLEXPAY_MERCHANT'),
    apiToken: (string) getenv('FLEXPAY_TOKEN'),
    callbackToken: (string) getenv('FLEXPAY_CALLBACK_TOKEN'),
    timeoutSeconds: 30
);
$client = new FlexPayClient(
    $configuration,
    new GuzzleClient(['timeout' => 30, 'http_errors' => false]),
    $factory,
    $factory,
    $logger // LoggerInterface fourni par l’application, ou null.
);
```

L’environnement sandbox/production est déterminé par les URLs et identifiants fournis par l’application ; le package n’invente pas de bascule d’environnement. Voir [configuration](docs/configuration.md).

## Mobile Money

```php
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;

$created = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
    amount: 50,
    currency: 'USD',
    phoneNumber: '+243970000003',
    merchantReference: 'ORDER-2026-0001',
    callbackUrl: 'https://merchant.example/payments/flexpay/callback'
));

if ($created->accepted && $created->providerTransactionId !== null) {
    // Conserver l’identifiant et vérifier plus tard côté serveur.
    $status = $client->transactions()->getStatus($created->providerTransactionId);
    $confirmed = $status->normalizedStatus->value === 'successful'
        && hash_equals($created->providerTransactionId, (string) $status->providerTransactionId)
        && hash_equals('ORDER-2026-0001', $status->transactionReference)
        && abs($status->amount - 50) <= 0.00001
        && hash_equals('USD', $status->currency);
}
```

Un résultat `pending` n’est pas une confirmation. Même lorsque `TransactionService` renvoie `successful`, l’application doit comparer l’identifiant, la référence, le montant et la devise à sa commande serveur avant tout effet métier. Une erreur ou un timeout de création est ambigu et ne doit pas déclencher une seconde création automatique avec une nouvelle référence.

## Carte hébergée

Le package ne construit plus une URL de paiement à partir du Bearer serveur. L’application doit injecter un adaptateur vérifié :

```php
use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;

/** @var HostedCardCheckoutInterface $adapterConfirme */
$client = new FlexPayClient(
    $configuration,
    $httpClient,
    $requestFactory,
    $streamFactory,
    $logger,
    $adapterConfirme
);
```

`$client->card()->createPayment($request)` retourne ensuite un `CardPaymentResult`. L’URL est contrôlée (HTTPS, hôte exact, aucun identifiant URL, aucun token API) avant d’être exposée. Le retour navigateur et le callback restent de simples signaux : `TransactionService` doit confirmer côté serveur l’identifiant, la référence, le montant et la devise. Un adaptateur exécutable fake est fourni dans [l’exemple PHP natif](examples/plain-php/src/FakeHostedCardCheckout.php).

Le package ne collecte jamais PAN, CVV ou date d’expiration.

## Callback

```php
$event = $client->webhooks()->parse(
    getallheaders(),
    (string) file_get_contents('php://input'),
    $_GET
);

// Rechercher la transaction locale, dédupliquer, puis vérifier le statut côté serveur.
// Répondre rapidement ; mettre les effets métier lourds en queue.
```

Par défaut, le header `X-FlexPay-Callback-Token` doit correspondre à `callbackToken`. Ce token applicatif n’est pas une signature fournisseur inventée. `CheckoutManager::handleCallback()` implémente la corrélation et la déduplication, mais le store de production doit garantir les contraintes d’unicité et des transactions atomiques.

## Checkout et frontend optionnel

L’intégration sans interface appelle directement `FlexPayClient`. Le flux complet utilise `CheckoutManager` avec une implémentation de `CheckoutStoreInterface`. `InMemoryCheckoutStore` sert uniquement aux tests.

Pour publier les fichiers prêts à l’emploi sans Node.js chez le consommateur :

```bash
vendor/bin/flexpay-assets publish public/vendor/flexpay
```

Ajouter `--force` uniquement pour remplacer des fichiers réguliers existants. Le navigateur ne contacte jamais FlexPay directement et ne choisit jamais le montant. L’application fournit `checkoutId`, endpoints same-origin, CSRF et `allowedRedirectHosts`. Les exports sont `FlexPayCheckoutApi`, `Controller`, `Modal`, `CHECKOUT_EVENTS` et les messages i18n. Voir [checkout](docs/checkout.md).

## Gestion des erreurs

```php
use Holduix\FlexPay\Exception\ApiException;
use Holduix\FlexPay\Exception\AuthenticationException;
use Holduix\FlexPay\Exception\InvalidResponseException;
use Holduix\FlexPay\Exception\NetworkException;
use Holduix\FlexPay\Exception\TimeoutException;
use Holduix\FlexPay\Exception\ValidationException;

try {
    $result = $client->mobileMoney()->createPayment($request);
} catch (ValidationException $error) {
    // Requête locale invalide.
} catch (AuthenticationException $error) {
    // Identifiants refusés.
} catch (TimeoutException $error) {
    // $error->ambiguous indique qu’une création a pu atteindre le fournisseur.
} catch (NetworkException $error) {
    // Indisponibilité réseau.
} catch (InvalidResponseException $error) {
    // Réponse vide, trop grande ou illisible.
} catch (ApiException $error) {
    // Refus HTTP fournisseur ; utiliser correlationId pour le diagnostic.
}
```

## Sécurité et journalisation

- HTTPS est obligatoire hors opt-in local explicite ;
- Authorization, tokens, identifiants marchands, téléphones et données carte sont filtrés ;
- aucune réponse brute fournisseur ne doit être transmise au navigateur ;
- aucun statut de retour navigateur ou de callback ne confirme seul le paiement ;
- créations non idempotentes : une tentative, sans retry automatique ;
- lectures : retries bornés et backoff ;
- logs : statut HTTP, hôte, durée, tentative et identifiant de corrélation, sans URL complète ni référence marchande.

Lire [security](docs/security.md) et [limitations](docs/limitations.md) avant toute mise en production.

## Frameworks et exemples

- [PHP natif](examples/plain-php/README.md) : fake complet, stockage fichier de démonstration et lancement local ;
- [CodeIgniter 4](examples/codeigniter4/README.md) : Config, Services, contrôleur, routes, migration et store ;
- [Laravel](examples/laravel/README.md) : configuration, provider, middleware propriétaire, contrôleurs, migration et tests HTTP ;
- Symfony ou autre framework : injecter tout client PSR-18, factories PSR-17 et logger PSR-3.

## Tests et compatibilité

```bash
composer check
composer coverage       # nécessite Xdebug ou PCOV
composer assets:check
composer installation:test
composer release:check
```

La CI cible PHP 8.1 à 8.4, dépendances usuelles et minimales, PHPUnit, couverture de 85 %, PHPStan niveau maximal, PHPCS, audit Composer, exemples, JavaScript, build reproductible, en-têtes et installation vierge. Le test sandbox est désactivé sans opt-in et ne fait jamais partie de la preuve locale par défaut.

## Contribution, support et licence

Consulter [CONTRIBUTING.md](CONTRIBUTING.md), [SUPPORT.md](SUPPORT.md), [SECURITY.md](SECURITY.md), le [guide de release](docs/releasing.md) et la [référence API](docs/api-reference.md).

Licence MIT. FlexPay peut être une marque de son propriétaire ; voir [NOTICE.md](NOTICE.md).

## Attribution

FlexPay Intégration By Holsonmp - Holduix

Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix.

Released under the MIT License.
