# Contribuer

## Installation locale

```bash
composer install --no-interaction --prefer-dist
npm ci
composer check
```

Créer une branche dédiée et une pull request courte. Respecter PSR-12, PHPStan niveau maximal, les types stricts, les en-têtes de licence et les commentaires français lorsqu’un commentaire est utile. Ne dupliquer ni mapper, ni transport, ni comportement frontend.

Toute modification doit inclure les tests pertinents. Une nouvelle fixture indique sa source (`observée`, `documentation fournisseur`, `historique` ou `robustesse seulement`), ne contient aucun secret et n’est jamais présentée comme preuve actuelle sans validation. Un nouveau statut exige provenance, mapping exact, tests et documentation.

Règles de sécurité : aucun PAN/CVV, aucune clé réelle, aucun Bearer dans une URL, aucun callback cru comme succès, aucun retry de création sans idempotence prouvée et aucune réponse fournisseur brute au navigateur. Signaler une vulnérabilité selon [SECURITY.md](SECURITY.md).

Avant la pull request :

```bash
composer release:check
composer coverage
git diff --check
```

Mettre à jour `CHANGELOG.md` sous `Unreleased` pour tout changement utilisateur, de compatibilité ou de sécurité. La création d’une branche, d’un tag ou d’une publication reste une action humaine du mainteneur.
