# Releasing

## Maturité recommandée

Version recommandée : **`0.3.0-beta.1`**. Le lot ajoute un contrat carte et durcit des comportements de sécurité ; il ne justifie pas `1.0.0` tant que les contrats fournisseur essentiels ne sont pas confirmés en sandbox et par documentation actuelle.

## Validation locale

```bash
composer install --no-interaction --prefer-dist
npm ci
composer release:check
composer coverage
composer archive --format=tar
git diff --check
```

Inspecter l’archive, vérifier les rapports d’audit, puis tester le tag candidat via le workflow de validation. Le workflow ne publie rien.

## Checklist GitHub et Packagist

1. Créer ou vérifier le dépôt GitHub public.
2. Configurer le remote sans secret dans l’URL.
3. Protéger la branche principale et exiger la CI.
4. Configurer les secrets GitHub nécessaires à la CI, sans secret marchand de production.
5. Exécuter `composer check`.
6. Exécuter les tests avec couverture et confirmer au moins 85 %.
7. Exécuter et inspecter `composer archive`.
8. Vérifier `CHANGELOG.md` et les changements de compatibilité.
9. Choisir manuellement la version SemVer.
10. Créer manuellement le commit de release.
11. Créer un tag signé ou annoté.
12. Pousser manuellement le tag.
13. Créer manuellement la release GitHub.
14. Soumettre le dépôt à Packagist.
15. Configurer la mise à jour automatique Packagist.
16. Vérifier l’installation depuis un projet vierge.
17. Vérifier les métadonnées affichées.
18. Vérifier l’archive téléchargée.

Avant toute publication publique, configurer un canal privé de signalement des vulnérabilités, obtenir la validation fournisseur des endpoints/statuts/callback/carte, exécuter les scénarios sandbox sans argent réel et faire valider la persistance/queue/monitoring de l’application cible.
