# Callbacks et webhooks

Dans la couche checkout, le callback reste un signal. `CheckoutManager::handleCallback()` calcule une empreinte locale, corrèle la session, compare les valeurs disponibles puis relit le statut FlexPay. Le body seul ne confirme jamais un paiement. L’application doit rendre `markCallbackProcessed()` atomique avec sa transaction de repository.

Le parser accepte JSON, formulaire URL-encodé et paramètres query optionnels. Si `callbackToken` est configuré, il cherche `token` dans query/body ou `X-FlexPay-Callback-Token` et utilise `hash_equals`.

```php
$event = $client->webhooks()->parse($headers, $rawBody, $_GET);
```

Au moins une référence marchande ou fournisseur est obligatoire. Aucune signature HMAC n’est implémentée : aucun algorithme, header ou secret de signature n’est confirmé.

Traitement applicatif recommandé :

1. parser ;
2. identifier et verrouiller le paiement local ;
3. détecter l’événement déjà traité ;
4. relire le statut fournisseur ;
5. comparer référence, montant et devise ;
6. appliquer une transition autorisée ;
7. enregistrer l’événement ;
8. émettre une seule fois les effets métier ;
9. répondre rapidement puis déléguer PDF/email/SMS à la queue applicative.

Le callback peut arriver avant ou après le retour navigateur et peut être répété. Le package ne persiste rien et ne garantit pas l’idempotence applicative.
