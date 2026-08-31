# Rapport historique d'extraction FlexPay

Le rapport consolidé de la couche checkout 0.2, incluant la machine à états, le frontend, les tests et l'intégration applicative, est disponible dans [`checkout-delivery-report.md`](checkout-delivery-report.md). Le contenu ci-dessous conserve la preuve de la première extraction du client 0.1.

Date : 31 août 2026

## 1. Résumé

L’intégration active utilise un POST C2B Mobile Money, un checkout carte/portail hébergé construit localement, deux endpoints GET de vérification et un callback GET/POST/JSON. Le SDK optionnel référencé n’est pas installé. Le package `holduix/flexpay-php` reproduit ces contrats avec PSR-18/17/7/3, DTO typés, erreurs, statuts normalisés, redaction et tests.

## 2. Fichiers legacy analysés

Voir l’inventaire détaillé dans `../../../docs/audit-existing-integration.md`. Les sources déterminantes sont `Config/FlexPay`, `FlexPayClient`, `PaymentService`, le contrôleur/requête callback, `PreorderApiController`, `PaymentModel`, les routes/filtres, le schéma `payments`, le JavaScript de polling/redirection, les vues et la suite d’intégration.

## 3–4. Flux Mobile Money et Visa

- Mobile Money : POST JSON C2B, téléphone réduit aux chiffres, confirmation différée et lecture serveur.
- Visa/carte : aucune donnée carte collectée ; URL GET vers checkout hébergé, retour navigateur non probant et confirmation callback/statut.

## 5. Endpoints confirmés

- `POST https://backend.flexpay.cd/api/rest/v1/paymentService`
- `GET https://backend.flexpay.cd/api/rest/v1/check/{providerReference}`
- `GET https://cardpayment.flexpay.cd/api/rest/v1/vpos/orderNumber/{orderNumber}`
- checkout `https://cardpayment.flexpay.cd/v3/pay`

## 6–7. Payloads et headers

Le C2B contient `merchant`, `type=1`, `reference`, `phone`, `amount`, `currency`, `callbackUrl`. Les appels API utilisent Accept/Content-Type JSON et Bearer. La query checkout reproduit les paramètres legacy documentés dans l’audit. Aucun header d’idempotence ou de signature n’a été inventé.

## 8. Statuts

Confirmés par fake/tests : `0/success`, `1/failed`, `2/pending`. Le package centralise aussi les mots déjà reconnus par le legacy et conserve toute valeur brute. Une valeur inconnue devient `unknown` sans transition automatique.

## 9–10. Callback et sécurité

Le callback accepte GET, formulaire et JSON. Le token applicatif optionnel est lu depuis query/form/header et comparé avec `hash_equals`. Aucune signature FlexPay n’est prouvée. Le package recommande et permet la lecture serveur, mais l’idempotence/persistance restent applicatives.

Les secrets, données carte, téléphones complets et URLs checkout sont filtrés dans les logs/réponses brutes. Un timeout de création est ambigu et non retenté.

## 11. Non vérifié ou non pris en charge

Réponses de production, sandbox, taux limites, expiration réelle, 3-D Secure, signature officielle, remboursement, B2C, annulation fournisseur, opérateur et clé d’idempotence FlexPay.

## 12. Architecture

`FlexPayClient` expose quatre services. Le transport commun reçoit ses dépendances PSR par injection. Configuration, validation, mapping, DTO, exceptions et redaction restent indépendants du framework. L’adaptateur CodeIgniter traduit les résultats vers le tableau historique.

## 13. Fichiers créés

- package complet sous `packages/flexpay-php` : 97 fichiers hors caches/vendor lors du contrôle ;
- `composer.json`/`composer.lock` racine et exemple d’environnement ;
- interface, factory, adaptateur package et client HTTP fake applicatif ;
- audit racine et workflow CI dédié.

## 14. Fichiers modifiés

- `.gitignore` ;
- `app/Config/FlexPay.php` ;
- `app/Services/PaymentService.php` ;
- `app/Services/Payments/FlexPayClient.php` ;
- `tests/integration/preorder-payment-flow.sh`.

Le legacy n’a pas été supprimé. Les corrections du harness alignent le type de vente avec `order_type`, rendent les scénarios fake pending déterministes et empêchent un callback `code=0` de contourner un statut serveur encore pending.

## 15. Tests ajoutés

56 tests package, 142 assertions : configuration, validation, téléphone, payloads, checkout, mapping, redaction, DTO, callbacks, matrice HTTP, erreurs, timeout ambigu, retry GET et fixtures contractuelles. Le sandbox constitue le seul test ignoré par défaut.

## 16–17. Commandes et résultats

```text
node --test tests/unit/*.test.cjs
=> 6 tests, 6 succès

FLEXPAY_DRIVER=legacy node --test tests/integration/preorder-payment-flow.test.cjs
=> 1 suite, succès, fake mode uniquement

CI_ENVIRONMENT=development FLEXPAY_DRIVER=package node --test tests/integration/preorder-payment-flow.test.cjs
=> 1 suite, succès, fake mode uniquement

composer validate --strict (racine et package)
=> valide

composer install --no-interaction --prefer-dist (racine et package)
=> succès, lockfiles générés

composer audit --no-interaction (racine et package)
=> aucune vulnérabilité connue

packages/flexpay-php/vendor/bin/phpunit
=> 56 tests, 142 assertions, 1 sandbox ignoré

packages/flexpay-php/vendor/bin/phpstan analyse src tests
=> aucune erreur

packages/flexpay-php/vendor/bin/phpcs --standard=PSR12 src tests
=> aucun écart
```

La couverture chiffrée n’a pas pu être mesurée localement : l’exécutable PHP ne fournit ni Xdebug ni PCOV. La CI installe Xdebug et publie `--coverage-text`; il reste à confirmer le seuil de 85 % sur le runner.

## 18. Installation

À la racine : `composer install`. CodeIgniter découvre automatiquement `vendor/autoload.php`. Garder `FLEXPAY_DRIVER=legacy` par défaut et renseigner les secrets uniquement hors Git.

## 19. Migration

Activer `CI_ENVIRONMENT=development`, `FLEXPAY_DRIVER=package` et d’abord `FLEXPAY_FAKE_MODE=1`. Comparer les fixtures, exécuter les tests, puis vérifier le sandbox une seule fois avec autorisation. Le factory refuse volontairement le package en production pendant cette phase.

## 20. Rollback

Remettre `FLEXPAY_DRIVER=legacy`. Aucune migration SQL et aucune suppression legacy n’étant réalisées, le rollback est immédiat et conserve les références existantes.

## 21. Risques restants

1. Obtenir de FlexPay des réponses sandbox/production anonymisées et la documentation de la version exacte.
2. Comparer systématiquement montant, devise et référence dans `PaymentService` avant confirmation locale.
3. Supprimer à terme le fallback « callback réussi sans vérification exploitable » après validation fournisseur.
4. Préférer un token callback en header ; le token query et l’URL checkout peuvent fuiter dans l’infrastructure externe.
5. Vérifier le seuil de couverture CI et exécuter la matrice PHP distante.
6. Autoriser le driver package en production uniquement après validation formelle et plan de surveillance.
