# Configuration

Toutes les valeurs sont injectées dans `FlexPayConfiguration`. Le package ne lit ni `$_ENV`, ni `getenv()`.

| Paramètre | Rôle | Défaut confirmé |
|---|---|---|
| `apiBaseUrl` | API C2B/statut | `https://backend.flexpay.cd` |
| `cardBaseUrl` | API/checkout carte | `https://cardpayment.flexpay.cd` |
| `merchantId` | code marchand | aucun |
| `apiToken` | Bearer et query checkout | aucun |
| `callbackToken` | contrôle applicatif optionnel | vide |
| `mobileMoneyEndpoint` | création C2B | `/api/rest/v1/paymentService` |
| `transactionStatusEndpoint` | statut générique | `/api/rest/v1/check` |
| `cardOrderStatusEndpoint` | statut carte | `/api/rest/v1/vpos/orderNumber` |
| `cardCheckoutEndpoint` | checkout hébergé | `/v3/pay` |
| `defaultCurrency` | défaut applicatif | `CDF` |
| `timeoutSeconds` | contrat de transport | 30 |

HTTPS est exigé. `allowInsecureUrls=true` existe uniquement pour le développement local. Configurez également le timeout sur le client PSR-18 concret.

Ne versionnez jamais token, code marchand privé ou token callback. Le fichier `config/flexpay.example.php` ne contient aucune valeur réelle.

## CheckoutConfiguration

| Paramètre | Défaut | Origine |
|---|---:|---|
| `allowedCheckoutHosts` | `cardpayment.flexpay.cd` | hôte observé dans le legacy |
| `allowInsecureCheckoutUrls` | `false` | sécurité de la librairie |
| `pollInitialDelayMilliseconds` | 0 | appel immédiat legacy |
| `pollIntervalMilliseconds` | 4 000 | intervalle legacy |
| `pollMaximumIntervalMilliseconds` | 10 000 | décision configurable de backoff |
| `pollTimeoutSeconds` | 180 | 45 × 4 secondes dans le legacy |
| `statusMinimumRefreshSeconds` | 4 | cache serveur ajouté |
| `maximumNetworkErrors` | 3 | décision configurable |
| `statusPathTemplate` | route HTTP recommandée | doit contenir `{checkoutId}` |

Les applications peuvent mapper les variables suivantes vers l’objet :

```dotenv
FLEXPAY_DRIVER=legacy
FLEXPAY_CHECKOUT_UI=legacy
FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION=false
FLEXPAY_CHECKOUT_POLL_INITIAL_DELAY_MS=0
FLEXPAY_CHECKOUT_POLL_INTERVAL_MS=4000
FLEXPAY_CHECKOUT_POLL_MAX_INTERVAL_MS=10000
FLEXPAY_CHECKOUT_POLL_TIMEOUT_SECONDS=180
FLEXPAY_STATUS_MIN_REFRESH_SECONDS=4
FLEXPAY_CARD_ALLOWED_CHECKOUT_HOSTS=cardpayment.flexpay.cd
FLEXPAY_CHECKOUT_SUCCESS_URL=
FLEXPAY_CHECKOUT_CANCEL_URL=
FLEXPAY_CALLBACK_URL=
```

Le package ne lit pas directement ces variables. `CheckoutFeaturePolicy` maintient la production sur `legacy` tant qu’une permission explicite n’est pas fournie.
