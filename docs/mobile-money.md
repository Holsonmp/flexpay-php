# Mobile Money

Pour le parcours complet avec session, polling et reprise, voir [mobile-money-checkout.md](mobile-money-checkout.md).

## Données requises

Montant positif à deux décimales, code devise de trois lettres, numéro de 8 à 15 chiffres après nettoyage, référence marchande non vide (128 caractères maximum) et callback HTTPS.

Le numéro est normalisé comme dans le legacy : tout caractère non numérique est supprimé. Aucun préfixe national n’est ajouté et aucun opérateur n’est déduit.

## Séquence

1. L’application persiste une référence unique.
2. Elle crée `MobileMoneyPaymentRequest`.
3. Le package émet un POST JSON C2B avec `type=1` et Bearer.
4. Un résultat accepté contient normalement `providerTransactionId` et un statut pending/successful.
5. L’application garde l’état local en attente.
6. Callback ou polling déclenche une lecture serveur.
7. Montant, devise et référence sont comparés localement avant confirmation.

Un timeout de création est ambigu : conservez la référence, vérifiez le statut et ne recréez pas silencieusement le paiement.

Erreurs opérateur, solde insuffisant et numéro refusé n’ont pas été observés dans le dépôt. Ils restent « à vérifier » avec des réponses FlexPay anonymisées.
