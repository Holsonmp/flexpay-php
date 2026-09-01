# Rapport de préparation à la release

Date : 1er septembre 2026. Décision : **READY FOR PRE-RELEASE** pour `0.3.0-beta.1`, sous réserve des blocages listés en section 32. Le dépôt n’est pas déclaré prêt pour une release publique stable ou la production.

## 1. Résumé exécutif

Le dépôt a été transformé d’une extraction encore liée à un projet parent en package Composer autonome, documenté, testable et distribuable. Les deux causes de sévérité élevée ont été corrigées : exposition du Bearer dans le flux carte et confirmation possible avec un identifiant fournisseur non fiable. Une revue indépendante a ensuite découvert quatre contournements/races complémentaires ; ils ont été corrigés et couverts par régression.

Aucun commit, tag, push, dépôt GitHub, release, publication Packagist ou paiement réel n’a été effectué.

## 2. État initial

- branche `main`, worktree propre, commit visible `1f25467`, aucun remote configuré ;
- PHP 8.3.29, Composer 2.7.1, Node 22.21.1, npm 11.16.0 ;
- 115 tests PHP, 272 assertions, 1 test sandbox ignoré ; 23 tests JavaScript ;
- Composer/PHPStan/build verts, PHPCS inutilisable sans configuration ;
- workflows et documentation encore liés à `packages/flexpay-php` ;
- carte construisant une URL navigable avec le Bearer, callbacks et corrélation trop permissifs ;
- aucune extension Xdebug/PCOV locale.

Le détail reproductible est dans `docs/audits/baseline-report.md`.

## 3. Fonctionnalités vérifiées

Avec fakes et tests locaux : validation/configuration, Mobile Money, payload serveur, normalisation téléphone, erreurs HTTP typées, retry des lectures, absence de retry de création, statuts exacts, callback authentifié et borné, checkout persistant, transitions, reprise après erreur réseau, candidat fournisseur non fiable/rejouable, corrélation complète, carte désactivée sans adaptateur, adaptateur carte sûr, frontend, publication atomique des assets et exemples PHP/CI4/Laravel.

## 4. Fonctionnalités non vérifiées

Non prouvés : contrat fournisseur actuel, endpoints/credentials réels, valeurs de statuts réelles, signature callback, création carte/3DS, sandbox fournisseur, production, comportement réseau réel, stores framework exécutés dans une application complète, outbox/queue/monitoring et couverture locale chiffrée. Les fixtures ne remplacent aucune de ces preuves.

## 5. Architecture finale

`FlexPayClient` assemble les services PSR-18/17/3. Les services de paiement et de statut restent indépendants des frameworks. `CheckoutManager` coordonne l’agrégat, le store, la machine à états et la vérification serveur. Un identifiant reçu de l’initiation, du navigateur ou du callback est persisté dans `provider_transaction_candidate`, jamais recherché comme identifiant confirmé, puis lié seulement après une lecture fournisseur cohérente. Les sources frontend produisent trois artefacts `dist` reproductibles.

## 6. API publique finale

Les symboles existants sont conservés. Les ajouts sont `HostedCardCheckoutInterface`, des options de budgets/authentification sûres ajoutées en fin de constructeur et l’accès au candidat fournisseur de session. La carte sans adaptateur lève désormais `UnsupportedOperationException`, rupture sécuritaire assumée en pré-1.0. La cartographie complète est dans `docs/api-reference.md`.

## 7. Problèmes trouvés

- Bearer serveur dans une URL carte navigable et persistée ;
- identifiant externe lié avant vérification et succès sparse possible ;
- mapping par valeurs ambiguës et statut public successful incomplet ;
- double encodage permettant de masquer un secret dans l’URL carte ;
- candidat callback perdu après panne/crash et retry throttlé dédupliqué ;
- callbacks ouverts ou insuffisamment bornés ;
- logs/réponses invalides trop riches ;
- publication d’assets exposée aux symlinks/remplacements non atomiques ;
- exemples framework avec autorisation propriétaire incomplète ;
- workflows parent, documentation dupliquée et métadonnées de distribution incomplètes.

## 8. Problèmes corrigés

Carte fail-closed par défaut, adaptateur explicite, URL HTTPS/hôte exact/userinfo refusée, décodage imbriqué et secrets serveur refusés, URL non persistée, candidat fournisseur séparé, seconde lecture après succès d’initiation, tuple complet obligatoire, mismatch refusé, callback rejouable après panne, mapping exact, auth avant parsing, budgets de corps/réponse, redaction renforcée, assets atomiques anti-symlink, frontend fail-closed, exemples autorisés et outillage de release centralisé.

## 9. Problèmes non corrigés

- exactement-once des effets externes : nécessite une transaction store + outbox applicative ;
- signature cryptographique fournisseur : aucun contrat fiable disponible ;
- purge effective des anciennes URLs en base/sauvegarde : procédure documentée, exécution à la charge de chaque application ;
- validation opérationnelle/sandbox : impossible sans fournisseur et identifiants autorisés ;
- canal privé de signalement : à configurer sur l’hébergeur avant publication.

## 10. Vulnérabilités trouvées

Le scan scellé `7a627d7b-254d-44cd-91c6-aad2cb237f6c` a couvert 6 028 fichiers et 20 revues : 36 occurrences brutes, dont 7 élevées, 19 moyennes, 9 faibles, 1 informationnelle et 0 critique. Les occurrences élevées se regroupaient en deux causes racines corrigées. La revue indépendante finale a trouvé un bypass élevé et trois chemins moyens supplémentaires, tous corrigés avant le gate final. Son cycle de réconciliation ciblé n’a ensuite reproduit aucun contournement : 5 tests/23 assertions sur les flux sparse/mismatch/retry et 3 cas/3 assertions sur les secrets encodés ont réussi.

Mesure du scan : 115 threads, 460 624 496 tokens totaux, 456 764 950 tokens d’entrée dont 432 594 176 en cache, 3 859 546 tokens de sortie et 1 468 173 tokens de raisonnement.

## 11. Mesures de sécurité

HTTPS par défaut, secrets hors frontend, callback token header-only et constant-time, opt-outs explicites, limites taille/profondeur/champs, corrélation serveur complète, lecture fournisseur obligatoire, états fail-closed, versions optimistes, déduplication après vérification, réponses publiques filtrées, logs minimisés, allowlist de redirection, publication atomique, actions GitHub épinglées et audits Composer/npm.

## 12. Fichiers supprimés

- `.github/.DS_Store` ;
- `.github/workflows/flexpay-package.yml`, `production.yml`, `tests.yml` ;
- `examples/codeigniter4/app/Controllers/FlexPayCallbackController.php` ;
- `examples/codeigniter4/app/Services/PaymentApplicationService.php` ;
- `phpstan.neon`, `phpunit.xml` ;
- les documents fusionnés/supprimés : `audit-existing-integration.md`, `callbacks-and-webhooks.md`, `card-checkout.md`, `checkout-delivery-report.md`, `checkout-flow-audit.md`, `checkout-flow.md`, `checkout-state-machine.md`, `compatibility-matrix.md`, `delivery-report.md`, `error-handling.md`, `frontend-checkout.md`, `http-contract.md`, `installation.md`, `logging.md`, `migration-guide.md`, `mobile-money-checkout.md`, `transaction-status.md`.

## 13. Fichiers déplacés

Aucun déplacement opaque. Les documents dupliqués ont été fusionnés dans des fichiers canoniques puis les anciennes copies supprimées.

## 14. Fichiers créés

- gouvernance : `NOTICE.md`, `CONTRIBUTING.md`, `SECURITY.md`, `SUPPORT.md`, `CODE_OF_CONDUCT.md` ;
- Git/outillage : `.editorconfig`, `.gitattributes`, `phpunit.xml.dist`, `phpstan.neon.dist`, `phpcs.xml.dist`, `tools/*` ;
- GitHub : `ci.yml`, `release-validation.yml`, Dependabot, templates issues/PR ;
- runtime/test : `src/Contracts/HostedCardCheckoutInterface.php`, fakes carte et smoke PHP natif ;
- documentation canonique : API, callbacks, checkout, erreurs, limitations, releasing, mapping, READMEs frameworks et sept rapports d’audit dont celui-ci.

## 15. Dépendances supprimées

Aucune. Toutes les dépendances runtime PSR sont utilisées. Aucun framework n’a été imposé.

## 16. Dépendances ajoutées

Aucune dépendance runtime ou dev ajoutée au verrou. Guzzle a seulement été déclaré dans `suggest` comme implémentation possible PSR-18/PSR-7.

## 17. Tests ajoutés

Régressions pour carte désactivée/adaptateur malveillant, token clair/doublement encodé/callback secret, succès sparse à l’initiation/lecture/retour/callback, mismatch d’identifiant, candidat persistant après erreur réseau et throttle, callbacks/budgets/auth, redaction, réponse HTTP bornée, symlinks/assets, allowlist frontend, distribution, exemples et installation vierge.

## 18. Résultats PHPUnit

`composer release:check` : exit 0 ; **140 tests, 315 assertions, 1 test sandbox ignoré**, aucun échec. Le test ignoré exige des identifiants sandbox explicitement absents.

## 19. Couverture

`composer coverage` : exit 1 après réussite des 140 tests, car aucun driver Xdebug/PCOV n’est installé localement. Aucune valeur de couverture n’est revendiquée. La CI configure Xdebug et impose le seuil de 85 % via `scripts/check-coverage.php`; ce job doit réussir avant publication.

## 20. Résultats PHPStan

Niveau maximal configuré, 94 fichiers analysés, 0 erreur, exit 0.

## 21. Résultats PHPCS

PSR-12 via `phpcs.xml.dist`, exit 0.

## 22. Résultats JavaScript

`npm test` : 24 tests, 24 réussis, exit 0. `npm audit --audit-level=moderate` : 0 vulnérabilité, exit 0.

## 23. Résultat du build

`tools/check-dist.php` : build reproductible, `dist` synchronisé aux sources et bannières de licence conservées, exit 0.

## 24. Résultat composer validate

`composer validate --strict` : package valide, exit 0.

## 25. Résultat composer audit

`composer audit --locked --no-interaction` : aucune alerte connue, exit 0. Seule limite locale : cache Composer utilisateur non inscriptible, sans impact sur le résultat.

## 26. Résultat composer archive

Archive TAR finale de **501 760 octets et 141 entrées**, exit 0. Inspection du listing et du contenu extrait : aucun secret probable, chemin `/Users/`, ancien chemin parent, rapport interne, cache, vendor, node_modules, tests, outil interne, fixture, lock de développement ou archive imbriquée. README, LICENSE, NOTICE, CHANGELOG, documentation essentielle, `src`, `dist`, `bin`, config et exemples runtime présents. L’archive locale de contrôle a été supprimée après inspection.

## 27. Résultat du test d’installation vierge

`composer installation:test` : repository path, autoload, transport fake, Mobile Money, carte fake, callback et assets validés, exit 0. L’archive extraite a aussi passé `composer install --no-dev` et l’instanciation de `FlexPayConfiguration`; le dossier temporaire a été supprimé.

## 28. Résultat git diff --check

Exit 0, aucune erreur d’espace ou marqueur de conflit.

## 29. État de préparation GitHub

CI validation-only prête : PHP 8.1 à 8.4, dépendances lowest/locked, couverture Xdebug, frontend, installation vierge, audit et diff. Actions épinglées par SHA, permissions lecture, Dependabot et templates présents. Aucun remote ni dépôt public n’est configuré ; aucune CI distante n’a donc été observée.

## 30. État de préparation Packagist

Métadonnées Composer, licence, README, archive et installation path sont prêts. Le README précise que `composer require holduix/flexpay-php` n’est pas disponible avant publication. Restent manuels : dépôt public, URL/support/security, tag, release GitHub, soumission Packagist, hook de mise à jour et installation du paquet publié.

## 31. Version recommandée

`0.3.0-beta.1`. Les changements sécuritaires et le nouveau contrat carte justifient une mineure pré-1.0, mais l’absence de validation fournisseur interdit une stable/1.0.

## 32. Blocages avant publication

- obtenir et archiver la documentation fournisseur actuelle pour endpoints, auth, statuts, callbacks, carte et 3DS ;
- exécuter les scénarios sandbox autorisés sans argent réel ;
- faire réussir la couverture CI à au moins 85 % ;
- exécuter/tester la purge des anciennes `checkout_url` et faire tourner les tokens concernés ;
- valider le store SQL, l’outbox/queue, le rate limiting, le proxy, le monitoring et l’autorisation propriétaire dans l’application cible ;
- configurer un canal privé de vulnérabilité ;
- créer le dépôt/remote public et observer toute la CI verte.

## 33. Checklist de publication manuelle

1. Résoudre tous les blocages de la section 32.
2. Relire diff, API, changelog, compatibilité et attribution.
3. Exécuter `composer install` et `npm ci` depuis un checkout propre.
4. Exécuter `composer release:check`, `composer coverage`, `npm audit` et `git diff --check`.
5. Recréer et inspecter `composer archive`.
6. Vérifier l’installation depuis l’archive et un projet vierge.
7. Créer/configurer le dépôt GitHub public et protéger `main`.
8. Ouvrir une PR et exiger tous les jobs CI verts.
9. Choisir/confirmer manuellement `0.3.0-beta.1`.
10. Créer manuellement le commit de release.
11. Créer manuellement un tag signé ou annoté.
12. Pousser manuellement branche et tag.
13. Créer manuellement la release GitHub et vérifier son archive.
14. Soumettre manuellement le package à Packagist.
15. Configurer la mise à jour automatique Packagist.
16. Vérifier les métadonnées affichées et l’installation publiée.
17. Tester l’intégration sandbox de la version publiée.
18. Conserver les preuves et le plan de rollback ; ne passer stable qu’après validation fournisseur/opérationnelle.
