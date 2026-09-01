# Inventaire du dépôt

Inventaire final après nettoyage : 67 fichiers `src`, 24 fichiers de test PHP/JS/support, 38 fichiers d’exemples hors artefacts locaux, 10 fixtures, 7 sources frontend et 3 artefacts `dist`.

| Catégorie | Chemins | Responsabilité / exposition | Couverture et documentation | Décision |
|---|---|---|---|---|
| Point d’entrée public | `src/FlexPayClient.php` | Assemble client et services ; public | PHPUnit + README/API | conserver |
| Configuration | `src/Config/*` | API/runtime, checkout et retries ; public | unitaires + configuration | conserver, options sûres ajoutées en fin de constructeur |
| DTO/enums | `src/DTO/*`, `src/Enum/*` | Requêtes/résultats typés ; public | unitaires/contrats + API | conserver |
| Contrat carte | `src/Contracts/HostedCardCheckoutInterface.php` | Adaptateur public, expérimental | contrat fake + carte | créer pour désactiver le flux dangereux par défaut |
| Services | `src/Service/*` | Mobile Money, carte, statut, callback ; publics via client | contrats + guides dédiés | conserver/durcir |
| HTTP interne | `src/Http/*` | PSR-18, auth, budgets, retries | matrices HTTP + erreurs | conserver, documenter interne |
| Mapping/support/validation | `src/Mapper/*`, `src/Support/*`, `src/Validation/*` | extraction, status exact, redaction, téléphone | couverture unitaire | conserver, interne |
| Checkout | `src/Checkout/*` sauf store fake | agrégat, orchestration, contrats, états, serializer, policies | intégration + checkout/API | conserver, expérimental |
| Store fake | `src/Checkout/Support/InMemoryCheckoutStore.php` | tests/démonstration | intégration | conserver explicitement non production |
| Feature policy | `src/Checkout/Policy/CheckoutFeaturePolicy.php` | bascule legacy héritée, sans consommateur runtime | test + compatibilité | conserver temporairement pour BC 0.2 ; futur retrait majeur |
| Assets source | `resources/checkout/js/*`, `resources/checkout/css/*` | API/controller/modal/i18n/styles | 24 tests JS + checkout | conserver |
| Distribution | `dist/*` | ESM, IIFE, CSS sans Node consommateur | build reproductible/en-têtes | conserver |
| CLI | `bin/flexpay-assets` | publication bornée de `dist` | intégration assets | conserver |
| Exemples PHP | `examples/plain-php/*` | fakes et serveur local | lint/import/smoke | conserver, stockage non production |
| Exemples CI4 | `examples/codeigniter4/*` | config/services/store/routes/vue | syntaxe/import + README | conserver ; retirer l’ancien callback vide |
| Exemples Laravel | `examples/laravel/*` | provider/store/middleware/routes/vue/tests indicatifs | syntaxe/import + README | conserver ; garde propriétaire concrétisée |
| Fixtures | `fixtures/mobile-money`, `card`, `webhooks`, `http` | historique ou robustesse, jamais secret | tests + provenance docs | conserver |
| Tests | `tests/Unit`, `Contract`, `Integration`, `JavaScript`, `Support` | contrats locaux/fakes | CI | conserver et étendre |
| Outils | `tools/*`, `scripts/*` | headers, API, exemples, install, couverture, build | exécutés par Composer/CI | conserver, exclus archive runtime |
| Documentation utilisateur | `README.md`, `docs/*.md` | installation, API, flux, sécurité, release | liens/contrôles API | restructurer |
| Rapports internes | `docs/audits/*` | preuve et décision release | exclus archive | créer ; supprimer anciens rapports parent |
| Métadonnées | `composer.json/lock`, `package*.json`, configs `.dist`, `.editorconfig`, Git files | build/package/qualité | gates locaux/CI | normaliser |
| GitHub | `.github/workflows`, templates, Dependabot | CI et préparation GitHub | SHAs immuables | remplacer workflows hérités |
| Fichiers temporaires | `vendor`, `node_modules`, caches, coverage, var exemple, archives | local seulement | ignorés/export exclus | ne pas suivre |

Les classes internes techniquement accessibles par PSR-4 sont listées dans `docs/api-reference.md` sans nouvel `@internal`, afin d’éviter une rupture silencieuse. Les dépendances runtime restent toutes nécessaires aux contrats PSR ; aucune dépendance framework n’est imposée.
