# Sécurité

## Secrets et transport

Conserver identifiant marchand, API token et callback token dans le gestionnaire de secrets de l’application. HTTPS est obligatoire. Le Bearer est ajouté uniquement par `ApiTransport` aux appels serveur et n’apparaît jamais dans une URL carte. Le logger reçoit uniquement méthode, hôte, statut HTTP, durée, tentative, type d’erreur et corrélation.

La redaction filtre Authorization, tokens, secrets, identifiants marchands, mots de passe, PAN/CVV et téléphones, y compris des assignations dans du texte. Ne journalisez cependant pas sans nécessité `rawResponse` ou les exceptions complètes.

## Callbacks et corrélation

Le token callback est obligatoire par défaut, constant-time et accepté dans un header unique. Ce n’est pas une signature fournisseur. Utiliser rate limiting, limite de requête au proxy, HTTPS et idéalement une protection fournisseur officielle dès qu’elle est documentée.

Un retour navigateur ou callback ne confirme jamais seul le paiement. Une réussite exige une lecture serveur renvoyant le même identifiant demandé, la référence, le montant et la devise attendus. Une réponse incomplète reste non terminale ou devient `unknown`.

## Redirections et frontend

Les URLs carte exigent HTTPS, un hôte exact, aucune userinfo et aucune présence d’un secret serveur, y compris après décodages URL imbriqués. Le contrôleur JS refuse toute redirection sans allowlist. Le frontend communique same-origin, reçoit des réponses filtrées et ne détermine pas le montant. Le modal écrit les textes avec des APIs DOM contrôlées ; aucun message fournisseur brut ne doit être injecté.

## Retries, idempotence et stockage

- création : une seule tentative ; timeout/réseau ambigu ;
- lecture : retry borné, jitter et `Retry-After` plafonné ;
- checkout : référence marchande unique, identifiant fournisseur unique, version optimiste ;
- callback : identité sémantique unique et événement métier unique ;
- production : transaction SQL, queue/outbox, clés d’idempotence applicatives et rapprochement.

### Purge obligatoire après migration depuis 0.2

Les anciennes versions pouvaient conserver `checkout_url` dans `payload_json`. L’hydratation actuelle ignore ce champ, mais ne peut pas modifier les bases et sauvegardes de l’application. Avant déploiement :

1. inventorier les lignes et sauvegardes contenant `checkout_url`, `authorization` ou l’ancien Bearer ;
2. dans une transaction applicative, décoder chaque `payload_json`, supprimer exclusivement `checkout_url`, réencoder puis mettre à jour avec le contrôle de version habituel ;
3. vérifier l’absence résiduelle dans la base active, les exports, logs et caches ;
4. faire expirer ou tourner tout token API qui a pu être persisté ;
5. purger ou réécrire les sauvegardes selon la politique de rétention, avec validation de l’équipe sécurité.

Tester cette migration sur une copie avant production. Ne journaliser ni l’ancienne URL ni le secret pendant l’inventaire.

## Assets et supply chain

La publication refuse symlinks, fichiers non réguliers et écrasement sans `--force`, copie uniquement trois noms constants depuis `dist` et termine par rename atomique. Les workflows GitHub utilisent des SHAs immuables. Composer/NPM auditent leurs verrous ; `dist` est reconstruit puis comparé.

## Checklist production

Valider les endpoints/statuts/authentifications auprès du fournisseur, utiliser des identifiants dédiés, appliquer CSP/CSRF, définir rétention et accès aux logs, tester sandbox, monitorer erreurs/pending, alerter sur les rapprochements et faire relire l’implémentation carte/callback.
