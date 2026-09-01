# Audit fonctionnel

## Mobile Money

Confirmé par code et tests fakes : validation montant/devise/téléphone/URL, normalisation, référence, payload serveur, Bearer, pending avec identifiant, lectures pending/successful/failed, unknown, expiration locale, timeout/réseau/JSON invalide, reprise, double initialisation et arrêt terminal. La création n’est jamais retentée automatiquement.

Non vérifié en direct : endpoints, credentials, payload et valeurs actuelles du fournisseur. Les fixtures sont historiques ou de robustesse et aucune transaction réelle n’a été exécutée.

## Carte hébergée

Le flux ancien n’est plus supporté : il réutilisait le Bearer serveur dans l’URL. Le nouveau contrat exige un adaptateur injecté, contrôle HTTPS/hôte/userinfo/token et cohérence du résultat. L’URL est rendue dans l’action immédiate mais pas persistée. Les fakes valident persistance avant action, requires_action, retour, callback, ordres inversés, duplication, pending/successful/failed/cancelled/expired et mismatch.

La création de session publique et 3DS restent non vérifiés ; la carte est désactivée par défaut.

## Callbacks

Confirmé : body brut JSON/form, headers, token obligatoire, limites, référence, identité sémantique, comparaison montant/devise, événement ancien, vérification serveur, transitions et succès métier unique. Une variante avec champs étrangers reste un doublon. Aucun mécanisme de signature fournisseur n’est inventé.

## Machine à états et fiabilité

- états terminaux immuables ;
- succès jamais rétrogradé ;
- erreur réseau/timeout UI jamais transformé en échec ;
- identifiant d’initiation/navigateur/callback persistant comme candidat non fiable et rejouable jusqu’à corrélation complète ;
- refresh fournisseur limité et marqué avant l’appel ;
- même référence conservée ;
- réponses publiques filtrées ;
- frontend same-origin, allowlist obligatoire et polling arrêté au terminal.

Le listener est volontairement non bloquant après persistance. La livraison exactement-once des effets externes relève d’une outbox/queue transactionnelle applicative et reste un prérequis de production, pas une garantie du package.
