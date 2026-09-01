# Rapport de nettoyage

## Supprimés

- `.github/.DS_Store` : artefact macOS suivi ;
- `.github/workflows/production.yml` : déploiement parent obsolète, action locale absente et secret de production hors périmètre package ;
- `.github/workflows/flexpay-package.yml`, `.github/workflows/tests.yml` : doublons utilisant `packages/flexpay-php` ; remplacés par CI autonome ;
- `examples/codeigniter4/app/Controllers/FlexPayCallbackController.php` et `app/Services/PaymentApplicationService.php` : second moteur callback composé de commentaires non exécutables ; le contrôleur checkout vérifié reste source unique ;
- anciens rapports parent : `audit-existing-integration.md`, `checkout-delivery-report.md`, `checkout-flow-audit.md`, `delivery-report.md` ;
- documents dupliqués fusionnés : `callbacks-and-webhooks.md`, `card-checkout.md`, `checkout-flow.md`, `checkout-state-machine.md`, `error-handling.md`, `frontend-checkout.md`, `http-contract.md`, `installation.md`, `logging.md`, `migration-guide.md`, `mobile-money-checkout.md`, `transaction-status.md`, `compatibility-matrix.md` ;
- configurations locales `phpunit.xml` et `phpstan.neon`, remplacées par leurs variantes `.dist`.

Les références ont été recherchées avant suppression. Les documents utilisateurs ont été recréés sous les noms canoniques du dépôt autonome. Les tests/fixtures/dist utiles n’ont pas été supprimés.

## Fusionnés ou remplacés

- trois workflows → `ci.yml` et `release-validation.yml` validation-only ;
- documentation flux/états/frontend → `checkout.md` ;
- erreurs/logging/HTTP → `errors.md`, `security.md`, `architecture.md` ;
- installation/migration parent → README, quickstart et exemples autonomes.

## Dépendances et classes

Aucune dépendance runtime n’a été supprimée : toutes les interfaces PSR sont utilisées. Guzzle et Nyholm restent `require-dev` pour tests/exemples, sans imposer de framework. `package.json` est conservé parce que 24 tests JS et un build reproductible existent.

`CheckoutFeaturePolicy` n’est plus utilisée par le runtime autonome, mais reste temporairement pour compatibilité 0.2 ; sa suppression est réservée à une rupture annoncée. Aucun cache, `vendor`, `node_modules`, `var` de démo ou archive locale n’est suivi.

## Validation après nettoyage

PHPUnit, PHPStan, PHPCS, smoke exemples, tests JavaScript, build, en-têtes, API documentée, installation vierge, archive et `git diff --check` font partie du gate final décrit dans le rapport de release.
