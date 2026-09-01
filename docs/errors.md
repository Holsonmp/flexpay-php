# Erreurs

| Exception | Situation | Retry conseillé |
|---|---|---|
| `ConfigurationException` | URL, secret, devise, timeout ou budget local invalide | non, corriger la configuration |
| `ValidationException` | DTO/téléphone/identifiant/URL/adaptateur invalide | non, corriger l’entrée |
| `AuthenticationException` | HTTP 401 | non, corriger les identifiants |
| `AuthorizationException` | HTTP 403 | non sans changement de droit |
| `RateLimitException` | HTTP 429 | lecture seulement, backoff borné |
| `TimeoutException` | expiration réseau | lecture oui ; création non car ambiguë |
| `NetworkException` | panne PSR-18/réseau | selon `retryable`/`ambiguous` |
| `InvalidResponseException` | vide, JSON invalide, trop grand/profond | non automatique pour création |
| `ApiException` | autre HTTP non 2xx | selon statut et opération |
| `InvalidWebhookException` | token, format, taille ou référence callback invalide | répondre 4xx, ne rien appliquer |
| `UnsupportedOperationException` | carte sans adaptateur sûr | configurer une capacité confirmée |
| `CheckoutNotFoundException` | checkout absent/expiré du store | 404 |
| `CheckoutConflictException` | concurrence, double init, mismatch de corrélation | 409, rapprocher |
| `CheckoutRedirectException` | URL hors allowlist | refuser la redirection |
| `InvalidCheckoutTransitionException` | transition interdite | erreur applicative à diagnostiquer |

`FlexPayException` expose `httpStatus`, `providerCode`, `correlationId`, `merchantReference`, `providerTransactionId`, `rawResponse`, `retryable` et `ambiguous`. `rawResponse` est filtré, mais reste réservé aux logs serveur maîtrisés. Les réponses navigateur utilisent `CheckoutResponseSerializer`, jamais l’exception ou la réponse brute.
