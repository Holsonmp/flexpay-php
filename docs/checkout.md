# Checkout et frontend

## Machine à états

`created → choosing_method → collecting_details/initializing → pending` pour Mobile Money, ou `requires_action → redirecting → verifying` pour la carte. La vérification peut mener à `pending`, `requires_action`, `successful`, `failed`, `cancelled`, `expired` ou `unknown` selon les transitions autorisées.

Règles :

- les états terminaux ne sont jamais quittés ;
- un succès ne revient pas à pending ;
- réseau et timeout de polling deviennent pending/unknown, jamais failed ;
- une double initialisation est refusée ;
- un callback répété ne réémet pas `PaymentSucceeded` ;
- une observation ancienne est ignorée ;
- une référence candidate ne devient persistante qu’après corrélation fournisseur.

## Backend

Implémenter `CheckoutStoreInterface` avec contraintes uniques et contrôle de version, puis créer `CheckoutManager`. Le montant et la référence viennent de l’objet métier serveur ; les endpoints publics vérifient l’utilisateur propriétaire. Le serializer ne rend qu’une `CheckoutPublicView` et une action `none`, `poll` ou `redirect`.

## Sans interface / contrôleur / modal

- Sans interface : appeler les services PHP directement.
- Headless : instancier `FlexPayCheckoutController` avec `checkoutId`, endpoints same-origin, CSRF, fetch et `allowedRedirectHosts`.
- Modal : instancier `FlexPayCheckoutModal`/`Modal` avec les mêmes options et un trigger accessible.

```js
const modal = new FlexPayCheckout.Modal({
  trigger: document.querySelector("#pay"),
  checkoutId,
  endpoints,
  csrf: { header: "X-CSRF-TOKEN", token: csrfToken },
  allowedRedirectHosts: ["cardpayment.flexpay.cd"],
});
modal.mount();
```

Une allowlist vide refuse toute redirection. Le modal gère focus, Échap, reprise pending, traductions et nettoyage via `destroy()`. Il est optionnel.

## Assets, bundler et CSP

- Sans bundler : publier `dist` avec `vendor/bin/flexpay-assets publish <cible>` et charger IIFE/CSS.
- Avec bundler : importer `dist/flexpay-checkout.esm.js` ou les sources `resources` pendant le développement.
- CSP : servir les assets depuis le domaine de l’application, éviter les scripts inline et autoriser uniquement les endpoints same-origin nécessaires.
- Personnalisation : surcharger les variables/classes documentées du composant dans la feuille globale de l’application, sans modifier `dist` à la main.

Événements : `flexpay:opened`, `closed`, `method-selected`, `initializing`, `pending`, `redirecting`, `verifying`, `success`, `failed`, `cancelled`, `expired`, `unknown`, `error`. Le détail public exclut téléphone et payload brut.
