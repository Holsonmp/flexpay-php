# Exemple PHP natif

Copier `dist/` dans `public/vendor/flexpay`, puis lancer depuis ce dossier :

```bash
php -S 127.0.0.1:8080 -t public router.php
```

Le montant et la référence sont créés côté serveur. Le frontend transmet uniquement `checkoutId`, méthode et téléphone. `FileCheckoutStore` utilise `flock` pour la démonstration, mais n’offre ni base transactionnelle robuste, ni chiffrement, ni haute disponibilité : ne pas l’utiliser en production.
