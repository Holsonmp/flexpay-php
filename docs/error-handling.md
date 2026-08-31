# Gestion des erreurs

`ConfigurationException`, `ValidationException`, `AuthenticationException`, `AuthorizationException`, `ApiException`, `NetworkException`, `TimeoutException`, `RateLimitException`, `InvalidResponseException`, `InvalidWebhookException` et `UnsupportedOperationException` héritent de `FlexPayException`.

Une exception peut exposer de façon filtrée : statut HTTP, code fournisseur, correlation ID local, références, réponse brute, caractère retryable et résultat ambigu.

- création + timeout/réseau : non retryable automatiquement, `ambiguous=true` ;
- GET statut + erreur réseau/429/502/503/504 : retry possible si explicitement configuré ;
- JSON vide/invalide ou HTML : `InvalidResponseException` ;
- statut inconnu dans un JSON valide : résultat `unknown`, pas exception.

Les messages sont applicatifs et nomment le champ ou la nature de l’échec sans exposer de secret.
