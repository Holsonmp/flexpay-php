/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

document.addEventListener("DOMContentLoaded", () => {
  const create = document.querySelector("#create-checkout");
  const pay = document.querySelector("#pay-button");
  create.addEventListener("click", async () => {
    create.disabled = true;
    const response = await fetch("/demo/create", { method: "POST", credentials: "same-origin" });
    const checkout = await response.json();
    pay.hidden = false;
    const modal = new window.FlexPayCheckout.Modal({
      trigger: pay,
      checkoutId: checkout.checkout_id,
      endpoints: {
        details: "/payments/flexpay/checkouts/{checkoutId}",
        initialize: "/payments/flexpay/checkouts/{checkoutId}/initialize",
        status: "/payments/flexpay/checkouts/{checkoutId}/status",
      },
      allowedRedirectHosts: ["cardpayment.flexpay.cd"],
      // La démo ne quitte jamais localhost ; l’application réelle conserve la redirection activée.
      autoRedirect: false,
    });
    modal.mount();
    await modal.open();
  });
});
