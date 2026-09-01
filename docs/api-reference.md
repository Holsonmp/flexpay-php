# Référence API

La stabilité indiquée vaut pour la série pré-1.0 : toute rupture reste annoncée dans le changelog et le rapport de compatibilité. Les classes dites internes restent cartographiées parce que PSR-4 les rend techniquement accessibles ; elles ne sont pas un point d’extension recommandé.

## Points d’entrée stables

| Symbole | Responsabilité et API publique | Retours / exceptions | Stabilité |
|---|---|---|---|
| `Holduix\FlexPay\FlexPayClient` | Construit le client avec configuration, PSR-18, factories PSR-17, logger PSR-3 optionnel et adaptateur carte optionnel. `mobileMoney()`, `card()`, `transactions()`, `webhooks()`. | Services ci-dessous ; `ConfigurationException` à la construction. | stable 0.x |
| `Holduix\FlexPay\Config\FlexPayConfiguration` | Configuration API, endpoints, Bearer, callback, timeout, retries et budgets de corps. Toutes les propriétés sont `readonly`. | Instance validée ; `ConfigurationException`. | stable 0.x, nouveaux paramètres ajoutés en fin de constructeur |
| `Holduix\FlexPay\Config\CheckoutConfiguration` | Hôtes de redirection, rythmes de polling, seuil réseau et chemin public de statut. | Instance validée ; `ConfigurationException`. | expérimental |
| `Holduix\FlexPay\Config\RetryConfiguration` | Nombre de tentatives de lecture, délais et jitter. | `ConfigurationException`. | stable 0.x |
| `Holduix\FlexPay\Service\MobileMoneyService` | `createPayment(MobileMoneyPaymentRequest)`. | `MobileMoneyPaymentResult`; erreurs `FlexPayException`. | stable 0.x |
| `Holduix\FlexPay\Service\CardPaymentService` | `createPayment(CardPaymentRequest)` via adaptateur hébergé injecté. | `CardPaymentResult`; `UnsupportedOperationException` sans adaptateur, `ValidationException` si son résultat est dangereux. | expérimental |
| `Holduix\FlexPay\Service\TransactionService` | `getStatus(id, method)` et `getCardOrderStatus(id)`. Un successful incomplet ou dont l’identifiant diffère devient unknown ; le consommateur compare encore le tuple à sa commande. | `TransactionStatusResult`; erreurs transport/validation. | stable 0.x, contrats fournisseur à confirmer |
| `Holduix\FlexPay\Service\WebhookService` | `parse(headers, rawBody, query)` ; token, budgets et redaction. | `WebhookEvent`; `InvalidWebhookException`. | stable 0.x |
| `Holduix\FlexPay\Contracts\HostedCardCheckoutInterface` | `createCheckout(CardPaymentRequest, normalizedPhone)` ; capacité carte publique et sûre fournie par l’application. | `CardPaymentResult`. | expérimental |

### Exemple minimal

```php
$client = new Holduix\FlexPay\FlexPayClient(
    $configuration,
    $psr18Client,
    $requestFactory,
    $streamFactory,
    $logger
);
$result = $client->mobileMoney()->createPayment($request);
```

## DTO, résultats et enums

Les constructeurs exposent des propriétés publiques `readonly` dans l’ordre visible dans le code ; les résultats implémentent `JsonSerializable` lorsqu’ils offrent `toArray()`.

| Symbole | Paramètres / rôle | Stabilité |
|---|---|---|
| `Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest` | montant, devise, téléphone, référence marchande, callback, description optionnelle. | stable 0.x |
| `Holduix\FlexPay\DTO\Request\CardPaymentRequest` | montant, devise, référence, description, URLs callback/retour et moyen carte/portail. | expérimental |
| `Holduix\FlexPay\DTO\Response\PaymentResult` | base : statut normalisé/brut, réponse filtrée, références, méthode, montant, devise, message, date. | stable 0.x |
| `Holduix\FlexPay\DTO\Response\MobileMoneyPaymentResult` | ajoute `accepted`; `toArray()`. | stable 0.x |
| `Holduix\FlexPay\DTO\Response\CardPaymentResult` | ajoute `accepted` et `checkoutUrl`; `toArray()`. | expérimental |
| `Holduix\FlexPay\DTO\Response\TransactionStatusResult` | résultat d’une lecture fournisseur. Une donnée absente reste vide/null et n’est pas inventée. | stable 0.x |
| `Holduix\FlexPay\DTO\Response\WebhookEvent` | événement filtré : références, statut, montant/devise facultatifs et date. | stable 0.x |
| `Holduix\FlexPay\Enum\PaymentMethod` | `MOBILE_MONEY`, `CARD`, `PORTAL`. | stable 0.x |
| `Holduix\FlexPay\Enum\NormalizedPaymentStatus` | `CREATED`, `PENDING`, `PROCESSING`, `REQUIRES_ACTION`, `SUCCESSFUL`, `FAILED`, `CANCELLED`, `EXPIRED`, `UNKNOWN`; `isFinal()`, `isPending()`. | stable 0.x |

## Checkout applicatif

| Symbole | Responsabilité et méthodes publiques | Stabilité |
|---|---|---|
| `Holduix\FlexPay\Checkout\CheckoutManager` | `create`, `details`, `initialize`, `refreshStatus`, `handleReturn`, `handleCallback`. Coordonne store, serveur fournisseur et transitions. | expérimental |
| `Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface` | Recherche par checkout/référence/identifiant fournisseur, sauvegarde optimiste, transitions et déduplication callback. | expérimental, point d’extension |
| `Holduix\FlexPay\Checkout\Contracts\CheckoutEventListenerInterface` | `handle(CheckoutEvent)` après persistance de la transition. | expérimental, point d’extension |
| `Holduix\FlexPay\Checkout\CheckoutSession` | Agrégat persistant ; getters, candidat fournisseur non fiable/rejouable, sélection, observation, transition, sérialisation et hydratation. Ne persiste plus l’URL carte. | expérimental |
| `Holduix\FlexPay\Checkout\CheckoutPublicView` | Vue filtrée ; `fromSession`, `toArray`, `jsonSerialize`. | expérimental |
| `Holduix\FlexPay\Checkout\CheckoutAction` | `none`, `poll`, `redirect`, sérialisation. | expérimental |
| `Holduix\FlexPay\Checkout\CheckoutActionType` | `NONE`, `POLL`, `REDIRECT`. | expérimental |
| `Holduix\FlexPay\Checkout\CheckoutState` | États de l’orchestrateur et `isTerminal()`. | expérimental |
| `Holduix\FlexPay\Checkout\CheckoutTransition` | Transition versionnée et sérialisable. | expérimental |
| `Holduix\FlexPay\Checkout\Event\CheckoutEvent` | Nom, vue publique, date et transition facultative. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest` | Référence, montant/devise, méthodes, URLs et données client. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest` | checkout, méthode, téléphone facultatif. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest` | checkout et forçage serveur facultatif. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest` | checkout et query navigateur non fiable. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest` | headers, body brut et query non fiables. | expérimental |
| `Holduix\FlexPay\Checkout\DTO\CheckoutOperationResult` | vue publique, action et indicateur d’idempotence. | expérimental |
| `Holduix\FlexPay\Checkout\Http\CheckoutResponseSerializer` | Sérialise uniquement la vue/action filtrée et les erreurs checkout publiques. | expérimental |
| `Holduix\FlexPay\Checkout\Support\AssetPublisher` | `publish(target, overwrite=false)` ; trois fichiers `dist`, refus des symlinks et remplacement atomique. | stable 0.x |
| `Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore` | Store de test ; jamais production. | expérimental / test |

Les appels checkout lèvent `CheckoutException` ou une sous-classe lors d’un état interdit, d’une session absente, d’une corrélation invalide ou d’une redirection refusée.

## Exceptions publiques

| Symbole | Cas |
|---|---|
| `Holduix\FlexPay\Exception\FlexPayException` | Base transport/fournisseur ; expose statut HTTP, codes, corrélation, références, réponse filtrée, `retryable`, `ambiguous`. |
| `Holduix\FlexPay\Exception\ConfigurationException` | Configuration locale invalide. |
| `Holduix\FlexPay\Exception\ValidationException` | Requête locale ou résultat adaptateur invalide. |
| `Holduix\FlexPay\Exception\AuthenticationException` | HTTP 401. |
| `Holduix\FlexPay\Exception\AuthorizationException` | HTTP 403. |
| `Holduix\FlexPay\Exception\RateLimitException` | HTTP 429. |
| `Holduix\FlexPay\Exception\ApiException` | Autre réponse HTTP non 2xx. |
| `Holduix\FlexPay\Exception\NetworkException` | Erreur PSR-18/réseau. |
| `Holduix\FlexPay\Exception\TimeoutException` | Timeout détecté. |
| `Holduix\FlexPay\Exception\InvalidResponseException` | Corps vide, trop grand, trop profond ou JSON invalide. |
| `Holduix\FlexPay\Exception\InvalidWebhookException` | Authentification, format ou budget callback invalide. |
| `Holduix\FlexPay\Exception\UnsupportedOperationException` | Capacité non configurée, notamment carte. |
| `Holduix\FlexPay\Checkout\Exception\CheckoutException` | Base avec `errorCode` et `publicMessage`. |
| `Holduix\FlexPay\Checkout\Exception\CheckoutConflictException` | Concurrence, double initialisation ou corrélation incohérente. |
| `Holduix\FlexPay\Checkout\Exception\CheckoutNotFoundException` | Session inconnue. |
| `Holduix\FlexPay\Checkout\Exception\CheckoutRedirectException` | URL de redirection refusée. |
| `Holduix\FlexPay\Checkout\Exception\InvalidCheckoutTransitionException` | Transition non autorisée. |

## Classes d’implémentation cartographiées

Ces symboles sont publics au sens PHP mais considérés internes. Les applications doivent privilégier `FlexPayClient`, les DTO et les contrats :

- `Holduix\FlexPay\Http\ApiTransport`, `Holduix\FlexPay\Http\HttpResult` ;
- `Holduix\FlexPay\Mapper\StatusMapper`, `Holduix\FlexPay\Mapper\StatusMapping` ;
- `Holduix\FlexPay\Support\DataExtractor`, `Holduix\FlexPay\Support\PhoneNumberNormalizer`, `Holduix\FlexPay\Support\Redactor` ;
- `Holduix\FlexPay\Validation\RequestValidator` ;
- `Holduix\FlexPay\Checkout\Mapper\CheckoutStatusMapper` ;
- `Holduix\FlexPay\Checkout\Policy\CheckoutStateMachine`, `Holduix\FlexPay\Checkout\Policy\StatusRefreshPolicy` ;
- `Holduix\FlexPay\Checkout\Security\CheckoutRedirectValidator`.

`Holduix\FlexPay\Checkout\Policy\CheckoutFeaturePolicy` est conservée uniquement pour compatibilité avec la version 0.2 ; elle est dépréciée conceptuellement car un package autonome ne choisit pas entre un ancien projet et lui-même.

## API JavaScript et distribution

Exports ESM/IIFE stables en 0.x :

- `FlexPayCheckoutApi(options)` et `FlexPayCheckoutHttpError` ;
- `FlexPayCheckoutController(options)` et alias `Controller` ;
- `FlexPayCheckoutModal(options)` et alias `Modal` ;
- `CHECKOUT_EVENTS` ;
- `FLEXPAY_CHECKOUT_MESSAGES` et `checkoutMessages(locale, overrides)`.

Événements : `flexpay:opened`, `closed`, `method-selected`, `initializing`, `pending`, `redirecting`, `verifying`, `success`, `failed`, `cancelled`, `expired`, `unknown`, `error`. Le détail est filtré et n’expose ni téléphone ni payload brut.

Fichiers distribués : `dist/flexpay-checkout.esm.js`, `dist/flexpay-checkout.iife.js`, `dist/flexpay-checkout.css` et binaire `bin/flexpay-assets`.
