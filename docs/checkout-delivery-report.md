# Rapport final de livraison du checkout FlexPay

Date : 1er septembre 2026

Projet intégrateur : Étreinte des Regrets

Branche : `codex/flexpay-php-package`

Ce rapport distingue les preuves locales en mode fake des comportements qui restent à confirmer avec FlexPay. Aucun paiement réel ni appel sandbox n'a été exécuté.

## 1. Résumé du parcours legacy retrouvé

Le formulaire Étreinte crée une commande via `POST /preorder/create`. Pour Mobile Money, `PaymentService` persiste d'abord une référence marchande, puis le client historique appelle le C2B et le navigateur interroge `GET /preorder/status`. Pour carte/portail, le serveur construit une URL de checkout hébergé FlexPay et le navigateur y est redirigé. Le callback GET/POST/JSON et le polling relisent le statut serveur, verrouillent le paiement et déclenchent une seule fois les effets métier. L'ancien SDK optionnel n'est pas installé : le chemin actif audité est le fallback HTTP/URL. Le détail sourcé est dans `../../../docs/audit-existing-integration.md` et `checkout-flow-audit.md`.

## 2. Différences entre le legacy et la nouvelle couche checkout

Le legacy mêle transport FlexPay, tableaux permissifs, persistance applicative et comportement de page. La nouvelle couche sépare le client FlexPay, l'orchestrateur de checkout, le store, la machine à états, le contrat HTTP, le contrôleur JavaScript headless et le modal optionnel. Elle introduit des DTO typés, transitions validées, verrouillage optimiste, callbacks idempotents, validation stricte des redirections HTTPS, absence de retry de création et état `unknown` en cas de réponse ambiguë. L'intégration Étreinte conserve ses routes et effets métier existants via des adaptateurs.

## 3. Architecture retenue

- `FlexPayClient` sans framework expose Mobile Money, carte, statuts et webhooks sur des interfaces PSR.
- `CheckoutManager` orchestre création, initialisation unique, rafraîchissement, retour et callback.
- `CheckoutStoreInterface` porte la persistance, les transitions, la concurrence et la déduplication des callbacks.
- `CheckoutStateMachine`, `StatusRefreshPolicy` et `CheckoutFeaturePolicy` isolent les politiques.
- `CheckoutResponseSerializer` produit une vue HTTP sans secrets ni payload fournisseur.
- Le JavaScript consomme uniquement cette vue publique ; le modal reste une couche optionnelle au-dessus du contrôleur headless.
- Les adaptateurs CodeIgniter, Laravel et PHP natif restent hors du cœur.

## 4. Machine à états

Les états sont `created`, `choosing_method`, `collecting_details`, `initializing`, `requires_action`, `redirecting`, `pending`, `verifying`, `successful`, `failed`, `cancelled`, `expired` et `unknown`. Les quatre états finaux sont immuables ; `successful` ne peut pas être dégradé. Un retour navigateur provoque une vérification serveur, jamais un succès direct. Une erreur réseau ambiguë ne devient pas un échec. La matrice complète est dans `checkout-state-machine.md`.

## 5. API PHP publique ajoutée

- `CheckoutManager::create()`, `details()`, `initialize()`, `refreshStatus()`, `handleReturn()` et `handleCallback()`.
- DTO d'entrée pour chaque opération et `CheckoutOperationResult` en sortie.
- `CheckoutSession`, `CheckoutPublicView`, `CheckoutAction` et `CheckoutTransition`.
- `CheckoutStoreInterface` et `CheckoutEventListenerInterface` pour les applications.
- `CheckoutConfiguration`, `CheckoutFeaturePolicy`, `CheckoutRedirectValidator`, `AssetPublisher` et les exceptions checkout typées.
- L'API bas niveau existante demeure accessible par `FlexPayClient::mobileMoney()`, `card()`, `transactions()` et `webhooks()`.

## 6. Contrat HTTP ajouté

Le contrat recommande `POST /payments/flexpay/checkouts`, `GET /{checkoutId}`, `POST /{checkoutId}/initialize`, `GET /{checkoutId}/status`, `GET /{checkoutId}/return` et `POST /payments/flexpay/callback`. Les réponses exposent l'identifiant opaque, la référence marchande, le montant/devise, les méthodes autorisées, les statuts publics, le caractère terminal, le message et une action limitée à `none`, `poll` ou `redirect`. Les erreurs ont des codes 400/401/403/404/409/422/429/502/503/504 documentés dans `http-contract.md`.

## 7. API JavaScript ajoutée

`FlexPayCheckoutController` fournit `load`, `selectPaymentMethod`, `initializeMobileMoney`, `initializeCard`, `startPolling`, `resumePolling`, `stopPolling`, `cancelLocal` et `destroy`. Il est sans jQuery, fonctionne en ESM ou IIFE, envoie le CSRF configuré, empêche les doubles initialisations, maintient une seule requête de polling et suspend celle-ci quand la page est masquée. Les événements `flexpay:*` ne contiennent aucune donnée sensible.

## 8. Modal ajouté

`FlexPayCheckoutModal` est optionnel et utilise le contrôleur headless. Il gère choix Mobile Money/carte, saisie du téléphone, attente, redirection, succès, échec et état inconnu. Il fournit `role=dialog`, `aria-modal`, focus initial/restauré, piège de focus, fermeture contrôlée, zone `aria-live`, français/anglais et textes surchargeables. Les classes et variables CSS sont préfixées `flexpay-checkout-`/`--flexpay-*`, en px, sans style global concurrent.

## 9. Liste des fichiers créés

- Cœur package : `src/Checkout/**`, plus les DTO, services, transport, mapping, validation et sécurité sous `src/**`.
- Frontend : `resources/checkout/js/**`, `resources/checkout/css/flexpay-checkout.css`, `dist/flexpay-checkout.{esm.js,iife.js,css}`.
- Outillage : `bin/flexpay-assets`, `scripts/build-assets.mjs`, `scripts/check-coverage.php`, manifests Composer/npm et workflows CI.
- Tests/fixtures : `tests/{Unit,Contract,Integration,JavaScript,Support}/**` et `fixtures/**`.
- Exemples : `examples/codeigniter4/**`, `examples/laravel/**`, `examples/plain-php/**`.
- Documentation package : les guides `checkout-*`, `http-contract`, `frontend-checkout`, `framework-integration`, `quickstart`, Mobile Money et carte.
- Intégration Étreinte : configuration d'environnement, factory/interface/adaptateur/fake, adaptateur UI, assets publiés, tests unitaires et workflow racine.

## 10. Liste des fichiers modifiés

- Racine : `.gitignore`.
- Application : `app/Config/FlexPay.php`, `app/Controllers/HomeController.php`, `app/Services/PaymentService.php`, `app/Services/Payments/FlexPayClient.php`.
- UI existante : `app/Views/themes/magazine/index.php` et les partials `_header.php`/`_footer.php`.
- Test existant : `tests/integration/preorder-payment-flow.sh` pour aligner les attentes fake sur la logique produit actuelle.
- Package : les documents historiques `README.md`, `CHANGELOG.md`, configuration et guides initiaux ont été enrichis ; `delivery-report.md` renvoie vers le présent rapport complet.

Le legacy n'a pas été supprimé. Les anciennes valeurs par défaut contenant un jeton et un marchand ont été retirées de `app/Config/FlexPay.php`.

## 11. Tests ajoutés

La suite package couvre configuration, validation, statuts, transport HTTP, erreurs, redaction, contrats Mobile Money/carte/webhook, machine à états, concurrence, idempotence callback, ordre de persistance/événement, retours, redirections, timeout ambigu et publication d'assets. Les tests JavaScript couvrent API, CSRF, double clic, polling, visibilité, redirection, modal et accessibilité. Deux tests racine couvrent l'adaptateur Étreinte Mobile Money/carte, dont l'initialisation unique.

## 12. Résultats PHPUnit

`composer test` dans le package : **115 tests, 272 assertions, 1 test ignoré**, code de sortie 0. Le seul test ignoré est le sandbox volontairement désactivé. La CI calcule un Clover et refuse une couverture inférieure à 85 %. Le pourcentage n'est pas confirmé localement : ni Xdebug ni PCOV n'est disponible, et `phpdbg` ne fournit pas de driver de couverture dans cet environnement.

Les tests d'intégration racine passent en fake après la dernière modification : **1/1** avec `FLEXPAY_DRIVER=legacy` (environ 26 s), puis **1/1** avec `CI_ENVIRONMENT=development FLEXPAY_DRIVER=package` (environ 28 s). Ils couvrent Mobile Money, carte, attente et écritures en base sans appel fournisseur.

## 13. Résultats JavaScript

- Package : `npm test` → **23 tests, 23 succès**, code 0.
- Application : `node --test tests/unit/*.test.cjs` → **8 tests, 8 succès**, code 0.
- `npm run build` reconstruit les trois distributions ; leur diff est contrôlé en CI.
- QA navigateur fake : parcours Mobile Money jusqu'au succès en desktop et 390 × 844, sans débordement horizontal ; intégration Étreinte vérifiée avec un seul dialogue visible et focus dans le modal. Aucun moyen de paiement n'a été initialisé pendant la QA de l'application.

## 14. Résultats PHPStan

`composer analyse` (`phpstan analyse src tests`) : **aucune erreur**, code 0.

## 15. Résultats PHPCS

`composer style` (`phpcs --standard=PSR12 src tests bin/flexpay-assets`) : **aucun écart**, code 0. Les fichiers PHP des trois exemples passent également PSR-12.

## 16. Résultat Composer validate

`composer validate --strict` à la racine et dans le package : **deux manifests valides**, codes 0.

## 17. Résultat composer audit

`composer audit --no-interaction` à la racine et dans le package : **aucun avis de sécurité connu**, codes 0. `npm audit` du package rapporte également 0 vulnérabilité connue.

## 18. Résultat git diff --check

`git diff --check` : **aucune erreur d'espace ou de conflit**, code 0. Un contrôle complémentaire des fichiers non suivis ne trouve pas non plus d'espace final. Le scan ciblé ne trouve aucun secret ajouté dans les fichiers livrés ; les anciens secrets par défaut identifiés pendant l'audit ont été supprimés.

## 19. Procédure d'installation

Dans une application Composer, ajouter `holduix/flexpay-php`, exécuter `composer install`, injecter un client PSR-18, des factories PSR-17 et un logger PSR-3, puis construire `FlexPayConfiguration` et `CheckoutConfiguration` depuis les variables d'environnement. Implémenter `CheckoutStoreInterface` avec une contrainte unique sur la référence marchande et un contrôle de version. Ne placer aucun secret dans le code ou le navigateur. Pour le checkout générique, exposer ensuite les routes de `http-contract.md` avec authentification, autorisation, CSRF et limitation de débit applicatives.

Dans ce monorepo, `composer install` à la racine installe le package via le repository `path`. Copier `.env.flexpay.example` vers une configuration locale non versionnée et conserver les deux drivers à `legacy` au départ.

## 20. Procédure de publication des assets

Le package contient déjà les distributions compilées ; Node.js n'est donc pas requis en production :

```bash
vendor/bin/flexpay-assets publish public/vendor/flexpay-checkout
```

Ajouter `--force` uniquement pour remplacer une version déjà publiée. Dans Étreinte, la destination est `assets/vendor/flexpay-checkout`. Charger le CSS et l'IIFE uniquement lorsque `FLEXPAY_CHECKOUT_UI=package`. Node.js n'est utile que pour modifier les sources et exécuter `npm run build`.

## 21. Exemple d'intégration CodeIgniter

`examples/codeigniter4` fournit configuration/services, migration, store CodeIgniter, service applicatif, contrôleurs checkout/callback, routes, vue et script de page. Le callback est exclu du CSRF mais protégé par le secret applicatif documenté ; les routes navigateur conservent CSRF et contrôle de propriété. L'intégration Étreinte est volontairement transitoire : elle réutilise `/preorder/create` et `/preorder/status` afin de conserver la commande, le stock et les effets métier validés.

## 22. Exemple d'intégration Laravel

`examples/laravel` fournit `FlexPayServiceProvider`, configuration, migration Eloquent, modèle/store, FormRequest, middleware de propriété, contrôleurs, routes, événement/listener/job et vue Blade. Les tests Feature montrent l'isolation par utilisateur et le traitement idempotent du callback ; ils sont des fichiers d'exemple à copier dans une application Laravel complète.

## 23. Exemple PHP natif

`examples/plain-php` est exécutable sans framework avec un store fichier et un faux transport local :

```bash
php -S 127.0.0.1:8080 examples/plain-php/router.php
```

Ouvrir `http://127.0.0.1:8080`. Le routeur sert les assets publiés et l'API de démonstration. Il ne contacte jamais FlexPay et ne convient pas comme store de production.

## 24. Procédure de migration

1. Installer le package et publier les assets sans changer le comportement actif.
2. Garder `FLEXPAY_DRIVER=legacy` et `FLEXPAY_CHECKOUT_UI=legacy` en production.
3. En développement fake, activer d'abord `FLEXPAY_CHECKOUT_UI=package` et tester l'adaptateur, l'accessibilité et les routes actuelles.
4. Activer ensuite `FLEXPAY_DRIVER=package`, toujours en fake, puis rejouer les suites legacy/package.
5. Valider en staging les callbacks, la vérification serveur, les effets métier et l'observabilité avec des identifiants autorisés.
6. Après accord fournisseur, définir explicitement `FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION=true` et activer séparément l'UI puis le driver.

Ne jamais lancer les deux drivers pour une même transaction. Étreinte ne requiert pas de migration SQL supplémentaire ; une nouvelle application doit utiliser les migrations/store des exemples.

## 25. Procédure de rollback

Remettre `FLEXPAY_CHECKOUT_UI=legacy` puis `FLEXPAY_DRIVER=legacy`, vider le cache de configuration et redéployer. Le code, les routes, le modal et le client historiques restent présents ; les références existantes restent lisibles. Les assets package peuvent rester publiés puisqu'ils ne sont pas chargés en mode legacy. Le rollback ne supprime aucune donnée et ne nécessite aucune migration descendante.

## 26. Limites restant à confirmer auprès de FlexPay

- Documentation/version exacte des endpoints, payloads et réponses de production.
- Identifiants sandbox et fixtures anonymisées couvrant Mobile Money et carte.
- Signature officielle des callbacks, politique de rotation, liste IP et protection anti-rejeu.
- Exhaustivité et temporalité des statuts, ordre callback/retour, identifiant d'événement et timestamps.
- Clé d'idempotence fournisseur, réconciliation après timeout de création et durée d'expiration.
- Limites de débit, `Retry-After`, fenêtres de polling et disponibilité de l'API de statut.
- Contrat du checkout carte : token de session, 3-D Secure, domaines de redirection, annulation et cartes prises en charge.
- Sémantique officielle de la devise, des décimales, du montant et de la référence retournés.
- Remboursement, B2C et annulation fournisseur, volontairement hors périmètre faute de preuve.
- Seuil de couverture de 85 % à confirmer sur le runner CI disposant de Xdebug ; il est bloquant dans le workflow mais non mesurable sur ce poste.

La production demeure forcée sur le legacy tant que ces contrats critiques et le plan d'exploitation ne sont pas validés.
