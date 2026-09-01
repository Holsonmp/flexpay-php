/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

document.addEventListener("DOMContentLoaded", () => {
  const trigger = document.querySelector("#pay-button");
  if (!trigger || !window.FlexPayCheckout) {
    return;
  }
  const modal = new window.FlexPayCheckout.FlexPayCheckoutModal({
    trigger,
    checkoutId: trigger.dataset.checkoutId,
    endpoints: {
      details: "/payments/flexpay/checkouts/{checkoutId}",
      initialize: "/payments/flexpay/checkouts/{checkoutId}/initialize",
      status: "/payments/flexpay/checkouts/{checkoutId}/status",
    },
    csrf: {
      header: "X-CSRF-TOKEN",
      token: document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || "",
    },
    allowedRedirectHosts: ["cardpayment.flexpay.cd"],
  });
  modal.mount();
});
