# Callbacks

## Entrée

Transmettre les headers, le body brut non pré-parsé et la query :

```php
$event = $client->webhooks()->parse($headers, $rawBody, $query);
```

Le service vérifie d’abord `X-FlexPay-Callback-Token`, puis la limite de taille. Il accepte un objet JSON ou un formulaire `application/x-www-form-urlencoded`. JSON tronqué, tableaux trop profonds, plus de 128 champs, clés/valeurs démesurées, header dupliqué et token query sont refusés par défaut.

Le token est une protection applicative configurable, pas une signature FlexPay. Aucune signature cryptographique fournisseur n’est implémentée sans contrat vérifiable.

## Traitement

1. Rechercher la session par référence marchande, puis identifiant fournisseur.
2. Calculer une identité sémantique de l’événement ; les champs étrangers ne contournent pas la déduplication.
3. Comparer référence, montant et devise lorsqu’ils sont présents.
4. Ignorer un événement plus ancien que la dernière observation fournisseur.
5. Interroger le fournisseur côté serveur, sous délai minimal de refresh.
6. Confirmer uniquement une réponse complète et corrélée.
7. Persister, enregistrer l’événement dédupliqué et répondre rapidement.

Le callback peut précéder ou suivre le retour navigateur. Aucun des deux n’est une preuve autonome. Une réponse HTTP d’accusé ne doit pas exposer le `checkoutId` interne. Les effets métier lourds partent en queue/outbox et sont eux-mêmes idempotents.

## Production

Le store doit assurer : contraintes uniques sur référence/identifiant/événement, mise à jour optimiste ou verrou, transaction adaptée, rétention des identifiants d’événement et possibilité de rapprochement. Le rate limiting se configure dans le framework ou le reverse proxy.
