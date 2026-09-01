# Démarrage fake en moins de dix minutes

```bash
composer install
php examples/plain-php/smoke.php
php -S 127.0.0.1:8080 -t examples/plain-php/public examples/plain-php/router.php
```

Le smoke charge Composer, un client PSR-18 fake, un adaptateur carte fake et un callback authentifié. Mobile Money retourne pending, la carte retourne une redirection simulée sans Bearer et le callback est parsé sans réseau.

Pour la démonstration navigateur :

```bash
vendor/bin/flexpay-assets publish examples/plain-php/public/vendor/flexpay
```

Ouvrir `http://127.0.0.1:8080`. Le fake passe de pending à successful après plusieurs lectures. Le stockage JSON utilise `flock`, reste local et doit être remplacé en production.

Validation complète :

```bash
composer examples:test
npm test
```

Aucun secret ni paiement réel n’est utilisé. Le fichier [FakeHostedCardCheckout.php](../examples/plain-php/src/FakeHostedCardCheckout.php) montre le contrat carte ; il ne décrit aucun endpoint fournisseur réel.
