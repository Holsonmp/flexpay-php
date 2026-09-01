# Carte hébergée

La bibliothèque ne manipule jamais PAN, CVV, date d’expiration ou données brutes de carte.

## Contrat sûr

Le flux historique construisait une URL navigateur avec le même Bearer que l’API serveur. Cette capacité est supprimée. Sans `HostedCardCheckoutInterface`, `CardPaymentService` lève `UnsupportedOperationException`.

L’adaptateur injecté doit être fondé sur une documentation ou validation fournisseur et retourner une URL publique/éphémère. Le package contrôle ensuite :

- URL valide, HTTPS hors fake local explicite ;
- hôte exactement égal à celui de `cardBaseUrl` ;
- absence de userinfo ;
- absence du token API, même encodé ;
- référence, montant, devise et méthode identiques à la requête.

La session est persistée en `requires_action`/`redirecting` avant de rendre l’action, mais l’URL complète n’est jamais écrite dans `CheckoutSession::toStorageArray()`.

## Retour et callback

L’identifiant reçu du navigateur ou du callback reste un candidat. Le serveur interroge le fournisseur avec ce candidat. Il ne le lie à la session que si la réponse renvoie exactement le même identifiant avec référence, montant et devise cohérents. Un statut `successful` incomplet est refusé et place la session en `unknown`.

Callback avant retour, retour avant callback et callbacks répétés sont testés avec fakes. Un paiement encore pending au retour reste pending. Le délai minimal de refresh empêche les signaux rapprochés de marteler le fournisseur.

Le comportement 3DS, la création d’une session publique, les statuts exacts et le mécanisme de callback carte restent à valider avec le fournisseur.
