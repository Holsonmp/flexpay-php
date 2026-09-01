# Changelog

Toutes les modifications notables sont documentées ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet vise le versionnage sémantique.

## Unreleased

### Added

- Contrat explicite `HostedCardCheckoutInterface` pour les intégrations carte confirmées par le fournisseur.
- Limites de taille pour callbacks et réponses HTTP, contrôles d’en-têtes, d’exemples, d’API et d’installation.
- Documentation de sécurité, de release, d’intégration et rapports d’audit autonomes.

### Changed

- Le package est préparé comme dépôt Composer autonome `holduix/flexpay-php`.
- Les callbacks exigent un token applicatif par défaut et la vérification serveur reste obligatoire.
- Les identifiants reçus de l’initiation, du navigateur ou d’un callback restent des candidats rejouables et ne sont liés qu’après corrélation fournisseur complète.
- Le mapping de statuts accepte uniquement les valeurs exactes documentées par les fixtures historiques.

### Deprecated

- Aucun élément public n’est déprécié dans ce lot.

### Removed

- Construction historique d’une URL carte avec le Bearer serveur.
- Workflows et rapports internes dépendant de l’ancien dépôt parent.

### Fixed

- Protection de la publication d’assets contre les liens symboliques et remplacements non atomiques.
- Redirections frontend refusées lorsqu’aucune liste d’hôtes n’est configurée.
- Réponses fournisseur invalides bornées et filtrées avant journalisation ou remontée d’erreur.
- Succès Mobile Money incomplets rétrogradés, double encodage de secrets refusé et callback non vérifié conservé après erreur réseau/throttle.

### Security

- Le token API serveur n’est plus placé dans une URL navigable ou persistée.
- Un retour navigateur ou callback non corrélé ne peut plus confirmer un paiement.
- Les anciennes URLs sensibles persistées disposent d’une procédure explicite de purge et de rotation des tokens.

## 0.2.0 - 2026-08-31

### Added

- Orchestrateur de checkout, machine à états, stockage abstrait et interface frontend optionnelle.

## 0.1.0 - 2026-08-31

### Added

- Première extraction des flux Mobile Money, statuts, erreurs typées et callbacks.
