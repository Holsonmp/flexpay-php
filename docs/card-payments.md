# Paiements carte

Pour la persistance avant redirection, la validation de l’URL et le retour utilisateur, voir [card-checkout.md](card-checkout.md).

Le comportement actif est un checkout hébergé. Le package ne reçoit jamais PAN, date d’expiration ou CVV.

`CardPaymentRequest` fournit montant, devise, référence, description, callback, URLs approve/cancel/decline/home, téléphone optionnel et méthode. `createPayment()` retourne immédiatement `CardPaymentResult::checkoutUrl`; l’application redirige ensuite le navigateur.

Les paramètres de query reproduisent exactement le legacy, y compris les trois variantes contenant le token. Traitez l’URL comme un secret transitoire : pas de log, pas d’analytics, pas de stockage durable. `rawResponse` contient une version filtrée.

Le retour navigateur n’est pas une confirmation. La confirmation doit venir d’une lecture serveur ou d’un callback vérifié. Le package expose `getCardOrderStatus()` pour l’endpoint `orderNumber`.

À vérifier : durée de session, 3-D Secure, token de session, statuts intermédiaires et comportement officiel en cas de fermeture du checkout. Annulation et remboursement fournisseur ne sont pas pris en charge.
