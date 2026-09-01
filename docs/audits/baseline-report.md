# Rapport de baseline

Date locale : 1er septembre 2026. État relevé avant modification.

## Git et outils

- racine : dépôt autonome `flexpay-php` ;
- branche : `main` ;
- commit visible : `1f25467 Add unit tests and support classes for FlexPay integration` ;
- remotes : aucun remote configuré, donc aucune URL/token distant exposé ;
- worktree : propre (`git status --short` sans sortie) ;
- PHP 8.3.29, Composer 2.7.1, Node 22.21.1, npm 11.16.0 ;
- historique visible : un commit.

## Dépendances principales

Runtime : PHP `^8.1`, PSR HTTP client/factory/message et PSR log. Développement : Guzzle, Nyholm PSR-7, PHPUnit 10.5, PHPStan 2.1, PHPCS 3.13, esbuild 0.25 et JSDOM 26.

## Commandes initiales

| Commande | Résultat avant modification |
|---|---|
| `composer validate --strict` | exit 0 |
| `composer install --no-interaction --prefer-dist` | exit 0 |
| `composer audit` | exit 0, aucune vulnérabilité connue ; avertissement cache Composer non inscriptible |
| `vendor/bin/phpunit` | exit 0, 115 tests, 272 assertions, 1 sandbox ignorée |
| `vendor/bin/phpstan analyse src tests` | exit 0, aucune erreur |
| `vendor/bin/phpcs` | exit 3 : aucun chemin/configuration par défaut |
| `npm ci` | exit 0, 0 vulnérabilité ; avertissement script esbuild et dépendance `whatwg-encoding` dépréciée |
| `npm test` | exit 0, 23 tests |
| `npm run build` | exit 0 |
| `git diff --check` | exit 0 |

## Problèmes présents

- workflows encore configurés pour `packages/flexpay-php` et workflow de déploiement hérité ;
- README et plusieurs rapports liés à l’ancien projet parent ;
- PHPCS inutilisable sans arguments ; absence des configurations `.dist` ;
- `.github/.DS_Store` suivi ; ignores/attributs Git incomplets ;
- carte historique exposant le Bearer dans l’URL et stockage ;
- corrélation fournisseur permissive, callbacks ouverts/illimités et status mapper fail-open ;
- exemples framework avec gardes d’autorisation non exécutables ;
- documentation de distribution, sécurité, support et release incomplète ;
- aucune extension Xdebug/PCOV locale, donc aucune mesure de couverture disponible sur ce poste.

Après reproduction, un lot de tests de sécurité volontairement rouge a produit 12 échecs PHP et 1 échec JavaScript avant correctif. Aucun test existant n’a été désactivé.
