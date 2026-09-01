# Exemple PHP natif

Depuis la racine du package, publier les assets puis lancer :

```bash
vendor/bin/flexpay-assets publish examples/plain-php/public/vendor/flexpay
php examples/plain-php/smoke.php
php -S 127.0.0.1:8080 -t examples/plain-php/public examples/plain-php/router.php
```

Le client et l’adaptateur carte sont des fakes : aucune requête FlexPay ni paiement réel. `FakeFlexPayHttpClient` produit un pending puis un success ; `FakeHostedCardCheckout` produit une URL hébergée simulée sans Bearer ; le callback utilise `fake-callback-token`.

Le montant et la référence sont créés côté serveur. Le frontend transmet uniquement `checkoutId`, méthode et téléphone. `FileCheckoutStore` utilise `flock`, mais n’offre ni base transactionnelle robuste, ni chiffrement, ni haute disponibilité : ne pas l’utiliser en production.

Le serveur de démonstration autorise HTTP local par opt-in. Une intégration réelle exige HTTPS, authentification/autorisation, CSRF, throttling, secrets externes, store transactionnel et queue/outbox.
