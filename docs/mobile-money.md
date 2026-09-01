# Mobile Money

## Flux

1. L’application calcule montant, devise et référence côté serveur.
2. `RequestValidator` exige un montant positif, une devise ISO à trois lettres, une référence non vide, une callback URL sûre et un téléphone normalisable.
3. `PhoneNumberNormalizer` retire la présentation et conserve 8 à 15 chiffres ; `+243 970 000 003` devient `243970000003`.
4. `MobileMoneyService::createPayment()` effectue une seule requête POST. Un timeout est ambigu et ne déclenche aucun retry de création.
5. Un résultat pending n’est accepté que si un identifiant fournisseur est présent. Un successful d’initiation exige en plus un tuple fournisseur complet et cohérent, puis l’orchestrateur impose une seconde lecture serveur.
6. L’application conserve la même référence et lit ultérieurement `TransactionService::getStatus()`. Avant tout effet métier, elle compare l’identifiant retourné, la référence, le montant et la devise à sa commande serveur.

## États testés avec fakes

- initiation `pending` avec identifiant ;
- lecture `pending`, puis `successful` corrélé par identifiant, référence, montant et devise ;
- `failed`, `unknown`, expiration locale, timeout, réseau et JSON invalide ;
- double initialisation refusée ;
- polling arrêté sur état terminal.

Les fixtures prouvent uniquement les valeurs `pending`, `success` et `failed` de leur source historique. Elles ne constituent pas une validation actuelle du fournisseur.

```php
$created = $client->mobileMoney()->createPayment($request);
if ($created->providerTransactionId !== null) {
    $status = $client->transactions()->getStatus($created->providerTransactionId);
    $confirmed = $status->normalizedStatus->value === 'successful'
        && hash_equals($created->providerTransactionId, (string) $status->providerTransactionId)
        && hash_equals($request->merchantReference, $status->transactionReference)
        && abs($status->amount - $request->amount) <= 0.00001
        && hash_equals(strtoupper($request->currency), $status->currency);
}
```

Ne recréez pas une transaction après un timeout sans rapprochement par la référence existante.
