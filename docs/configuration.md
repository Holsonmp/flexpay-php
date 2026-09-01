# Configuration

## FlexPayConfiguration

| Option | Type / défaut | Obligatoire | Impact |
|---|---|---|---|
| `apiBaseUrl` | string | oui | URL serveur Mobile Money/statut ; HTTPS obligatoire. |
| `cardBaseUrl` | string | oui | Base de statut/allowlist carte ; HTTPS obligatoire. |
| `merchantId` | string | oui | Identifiant serveur, filtré des réponses brutes. |
| `apiToken` | string | oui | Bearer serveur ; jamais navigateur, query ou log. |
| `callbackToken` | string, `''` | en pratique oui | Un callback est refusé par défaut lorsqu’il est vide. |
| `mobileMoneyEndpoint` | string, `/api/rest/v1/paymentService` | non | Contrat historique à confirmer avec le fournisseur. |
| `transactionStatusEndpoint` | string, `/api/rest/v1/check` | non | Contrat historique à confirmer. |
| `cardOrderStatusEndpoint` | string, `/api/rest/v1/vpos/orderNumber` | non | Contrat historique à confirmer. |
| `cardCheckoutEndpoint` | string, `/v3/pay` | non | Conservé pour compatibilité, non utilisé par le flux carte sûr. |
| `defaultCurrency` | string, `CDF` | non | Trois lettres ; les requêtes fournissent leur devise. |
| `timeoutSeconds` | int, `30` | non | 1–300 secondes ; le client PSR-18 doit recevoir un timeout cohérent. |
| `allowInsecureUrls` | bool, `false` | non | Réservé aux fakes locaux ; interdit en production. |
| `retry` | `RetryConfiguration` | non | Lectures seulement ; jamais une création ambiguë. |
| `allowUnauthenticatedCallbacks` | bool, `false` | non | Opt-out dangereux, uniquement si une protection externe prouvée existe. |
| `allowCallbackTokenInQuery` | bool, `false` | non | Les query strings fuient dans historiques/logs ; laisser faux. |
| `maxCallbackBodyBytes` | int, `65536` | non | 1 Ko–1 Mo. |
| `maxProviderResponseBytes` | int, `1048576` | non | 1 Ko–10 Mo. |

`RetryConfiguration` vaut par défaut 3 tentatives, 200 ms de base, 2 000 ms maximum et jitter. Elle ne s’applique qu’aux lectures marquées sûres.

## CheckoutConfiguration

| Option | Défaut | Rôle |
|---|---:|---|
| `allowedCheckoutHosts` | `cardpayment.flexpay.cd` | Correspondance exacte des hôtes de redirection. |
| `allowInsecureCheckoutUrls` | `false` | HTTP local explicite uniquement. |
| `pollInitialDelayMilliseconds` | `0` | Délai avant premier poll navigateur. |
| `pollIntervalMilliseconds` | `4000` | Poll applicatif minimal. |
| `pollMaximumIntervalMilliseconds` | `10000` | Backoff maximal. |
| `pollTimeoutSeconds` | `180` | Timeout UI ; devient `unknown`, jamais `failed`. |
| `statusMinimumRefreshSeconds` | `4` | Cache/throttle serveur fournisseur. |
| `maximumNetworkErrors` | `3` | Après ce seuil, état local `unknown`. |
| `statusPathTemplate` | `/payments/flexpay/checkouts/{checkoutId}/status` | Doit contenir `{checkoutId}`. |

Exemple :

```php
$configuration = new FlexPayConfiguration(
    apiBaseUrl: (string) getenv('FLEXPAY_URL_API'),
    cardBaseUrl: (string) getenv('FLEXPAY_URL_API_CARD'),
    merchantId: (string) getenv('FLEXPAY_MERCHANT'),
    apiToken: (string) getenv('FLEXPAY_TOKEN'),
    callbackToken: (string) getenv('FLEXPAY_CALLBACK_TOKEN')
);
```

Les variables restent une convention de l’application ; le package ne les lit pas lui-même.
