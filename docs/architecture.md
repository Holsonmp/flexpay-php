# Architecture

## Trois couches

1. `FlexPayClient` et ses services restent le client bas niveau : HTTP PSR-18, payloads confirmés, normalisation, erreurs, redaction et callback.
2. `CheckoutManager` orchestre session, persistance, transitions, vérification, retour et idempotence sans dépendance framework ou base de données.
3. `resources/checkout` et `dist/` fournissent une UI facultative : API HTTP de l’application uniquement, contrôleur headless puis modal construit au-dessus.

Le moteur n’appelle jamais une classe frontend. Le frontend n’appelle jamais FlexPay. Les effets commande, stock, billet, facture ou email restent derrière `CheckoutEventListenerInterface` dans l’application.

`FlexPayClient` assemble quatre services : `MobileMoneyService`, `CardPaymentService`, `TransactionService` et `WebhookService`. Le transport HTTP commun repose sur PSR-18/17/7, la journalisation sur PSR-3 et aucune classe ne lit directement l’environnement.

La configuration, les DTO de requête, les DTO de résultat, le mapping des statuts, la validation et la redaction sont séparés. Le package ne contient ni modèle, ni session, ni réponse framework, ni HTML, ni règle de commande.

Flux :

```text
Application -> DTO -> validation -> service -> transport PSR-18 -> FlexPay
                                      |                |
                                      v                v
                                status mapper     erreurs typées
                                      |
                                      v
                              résultat typé filtré
```

La carte constitue une exception confirmée : le service construit une URL de checkout hébergé et n’envoie pas de requête serveur. Les adaptateurs CodeIgniter/Laravel restent hors du cœur.

Les créations ne sont jamais retentées. Les lectures GET peuvent être retentées si `RetryConfiguration::maxAttempts` est explicitement supérieur à 1. La valeur par défaut reste 1, car aucune politique FlexPay n’est confirmée.
