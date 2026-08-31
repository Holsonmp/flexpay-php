# Frontend checkout

## Contrôleur headless

```js
import { FlexPayCheckoutController } from "/vendor/flexpay/flexpay-checkout.esm.js";

const checkout = new FlexPayCheckoutController({
  checkoutId: "chk_opaque",
  endpoints: {
    details: "/payments/flexpay/checkouts/{checkoutId}",
    initialize: "/payments/flexpay/checkouts/{checkoutId}/initialize",
    status: "/payments/flexpay/checkouts/{checkoutId}/status"
  },
  csrf: { header: "X-CSRF-TOKEN", token: "token-session" },
  allowedRedirectHosts: ["cardpayment.flexpay.cd"]
});

checkout.addEventListener("flexpay:success", event => {
  console.log(event.detail.checkoutId);
});

await checkout.initializeMobileMoney("+243970000003");
```

Méthodes : `load`, `selectPaymentMethod`, `initializeMobileMoney`, `initializeCard`, `startPolling`, `resumePolling`, `stopPolling`, `cancelLocal`, `destroy`.

Le polling est suspendu lorsque le document est masqué, n’exécute qu’une requête à la fois, respecte `next_poll_after_ms` et ne transforme pas son timeout en échec fournisseur.

## Modal

```js
import { FlexPayCheckoutModal } from "/vendor/flexpay/flexpay-checkout.esm.js";

const modal = new FlexPayCheckoutModal({
  trigger: "#pay-button",
  checkoutId: document.querySelector("#pay-button").dataset.checkoutId,
  endpoints: {
    details: "/payments/flexpay/checkouts/{checkoutId}",
    initialize: "/payments/flexpay/checkouts/{checkoutId}/initialize",
    status: "/payments/flexpay/checkouts/{checkoutId}/status"
  },
  locale: "fr",
  allowedRedirectHosts: ["cardpayment.flexpay.cd"]
});
modal.mount();
```

Le modal applique `role=dialog`, `aria-modal`, titre accessible, focus initial et restitué, piège de focus, Échap contrôlé, `aria-live`, labels visibles et `prefers-reduced-motion`.

## Événements

`flexpay:opened`, `closed`, `method-selected`, `initializing`, `pending`, `redirecting`, `verifying`, `success`, `failed`, `cancelled`, `expired`, `unknown`, `error`.

Le détail est limité à l’identifiant opaque, la référence marchande, la méthode, les deux statuts publics, `terminal` et le message. Aucun téléphone, token ou payload brut n’est diffusé.

## HTML sans bundler

```html
<link rel="stylesheet" href="/vendor/flexpay/flexpay-checkout.css">
<button type="button" id="pay-button" data-checkout-id="chk_opaque">Payer</button>
<script src="/vendor/flexpay/flexpay-checkout.iife.js" defer></script>
<script src="/assets/js/payment-page.js" defer></script>
```

`payment-page.js` contient le montage. Cette variante fonctionne avec une CSP sans script inline ; utilisez un nonce si un script inline est nécessaire.

## Personnalisation

Surcharger les variables `--flexpay-*` sur un conteneur ou `:root`. Toutes les classes sont préfixées `flexpay-checkout-`; aucun style global de bouton, input, titre ou lien n’est modifié.
