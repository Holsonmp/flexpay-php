# Checkout Mobile Money

Le serveur crée la session, valide la méthode autorisée et délègue la validation autoritaire du téléphone au client bas niveau. Le frontend effectue uniquement une validation de forme et n’appelle jamais FlexPay.

Après une création acceptée, le provider ID et l’état sont sauvegardés avant la réponse `poll`. Une initialisation répétée lève `checkout_already_initialized`; après un timeout ambigu, aucune seconde création automatique n’est effectuée.

La fermeture du modal arrête seulement le polling local. La session `pending` reste reprenable par son identifiant opaque. Un timeout d’affichage produit `unknown`, non terminal.
