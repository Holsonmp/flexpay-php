import test from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";

import {
  CHECKOUT_EVENTS,
  FlexPayCheckoutModal,
} from "../../resources/checkout/js/index.js";

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function fixture(overrides = {}) {
  return {
    checkout_id: "chk_modal",
    merchant_reference: "ORDER-MODAL",
    amount: 50,
    currency: "USD",
    allowed_methods: ["mobile_money", "card"],
    selected_method: null,
    status: "created",
    checkout_state: "created",
    terminal: false,
    ...overrides,
  };
}

function modalWithResponses(responses, overrides = {}) {
  const dom = new JSDOM(
    '<!doctype html><html><body><button id="pay-button">Payer</button></body></html>',
    { url: "https://example.com/checkout" },
  );
  const calls = [];
  const fetch = async (url, options) => {
    calls.push({ url, options });
    const next = responses.shift();
    if (typeof next === "function") {
      return next(url, options);
    }
    if (!next) {
      throw new Error("Réponse fetch manquante.");
    }
    return next;
  };
  const modal = new FlexPayCheckoutModal({
    trigger: "#pay-button",
    checkoutId: "chk_modal",
    endpoints: {
      details: "/checkouts/{checkoutId}",
      initialize: "/checkouts/{checkoutId}/initialize",
      status: "/checkouts/{checkoutId}/status",
    },
    document: dom.window.document,
    fetch,
    autoRedirect: false,
    setTimeout: () => 1,
    clearTimeout: () => {},
    ...overrides,
  });
  return { dom, modal, calls };
}

test("le modal s’ouvre depuis un bouton avec le contrat dialog accessible", async () => {
  const { dom, modal } = modalWithResponses([jsonResponse(fixture())]);
  modal.mount();
  await modal.open();

  const dialog = dom.window.document.querySelector('[role="dialog"]');
  assert.ok(dialog);
  assert.equal(dialog.getAttribute("aria-modal"), "true");
  assert.ok(dialog.getAttribute("aria-labelledby"));
  assert.equal(
    dom.window.document.querySelectorAll(".flexpay-checkout-method").length,
    2,
  );
  assert.match(dom.window.document.activeElement.className, /flexpay-checkout-method/);
  modal.destroy();
});

test("le choix Mobile Money affiche un champ labellisé", async () => {
  const { dom, modal } = modalWithResponses([jsonResponse(fixture())]);
  modal.mount();
  await modal.open();
  dom.window.document
    .querySelector('[data-payment-method="mobile_money"]')
    .click();

  const input = dom.window.document.querySelector("#flexpay-checkout-phone");
  assert.ok(input);
  assert.equal(input.labels[0].textContent, "Numéro Mobile Money");
  assert.equal(dom.window.document.activeElement, input);
  modal.destroy();
});

test("un téléphone vide produit une erreur associée sans requête", async () => {
  const { dom, modal, calls } = modalWithResponses([jsonResponse(fixture())]);
  modal.mount();
  await modal.open();
  dom.window.document
    .querySelector('[data-payment-method="mobile_money"]')
    .click();
  dom.window.document.querySelector("form").dispatchEvent(
    new dom.window.Event("submit", { bubbles: true, cancelable: true }),
  );

  const input = dom.window.document.querySelector("#flexpay-checkout-phone");
  assert.equal(input.getAttribute("aria-invalid"), "true");
  assert.match(
    dom.window.document.querySelector("#flexpay-checkout-phone-error").textContent,
    /format international/,
  );
  assert.equal(calls.length, 1);
  modal.destroy();
});

test("le bouton est neutralisé pendant l’initialisation Mobile Money", async () => {
  let resolveInitialize;
  const { dom, modal } = modalWithResponses([
    jsonResponse(fixture()),
    () =>
      new Promise((resolve) => {
        resolveInitialize = resolve;
      }),
  ]);
  modal.mount();
  await modal.open();
  dom.window.document
    .querySelector('[data-payment-method="mobile_money"]')
    .click();
  const input = dom.window.document.querySelector("#flexpay-checkout-phone");
  input.value = "+243970000003";
  const submit = dom.window.document.querySelector('button[type="submit"]');
  dom.window.document.querySelector("form").dispatchEvent(
    new dom.window.Event("submit", { bubbles: true, cancelable: true }),
  );
  assert.equal(submit.disabled, true);
  resolveInitialize(
    jsonResponse(
      fixture({
        selected_method: "mobile_money",
        status: "pending",
        checkout_state: "pending",
        action: { type: "poll", after_ms: 4000 },
      }),
    ),
  );
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.match(
    dom.window.document.querySelector(".flexpay-checkout-status-text").textContent,
    /téléphone/,
  );
  modal.destroy();
});

test("le modal reprend un checkout pending à la réouverture", async () => {
  const { dom, modal } = modalWithResponses([
    jsonResponse(
      fixture({
        selected_method: "mobile_money",
        status: "pending",
        checkout_state: "pending",
      }),
    ),
  ]);
  modal.mount();
  await modal.open();
  assert.equal(modal.pending, true);
  assert.match(
    dom.window.document.querySelector(".flexpay-checkout-status-text").textContent,
    /téléphone/,
  );
  modal.destroy();
});

test("la fermeture pending demande confirmation et rend le focus", async () => {
  let confirmationCalls = 0;
  const { dom, modal } = modalWithResponses([
    jsonResponse(fixture({ status: "pending", selected_method: "mobile_money" })),
  ], {
    confirmClose: () => {
      confirmationCalls++;
      return true;
    },
  });
  const trigger = dom.window.document.querySelector("#pay-button");
  trigger.focus();
  modal.mount();
  await modal.open();
  assert.equal(modal.close(), true);
  assert.equal(confirmationCalls, 1);
  assert.equal(dom.window.document.activeElement, trigger);
  modal.destroy();
});

test("la carte affiche la redirection sans navigation réelle", async () => {
  const { dom, modal } = modalWithResponses([
    jsonResponse(fixture()),
    jsonResponse(
      fixture({
        selected_method: "card",
        status: "requires_action",
        checkout_state: "redirecting",
        action: {
          type: "redirect",
          redirect_url: "https://cardpayment.flexpay.cd/v3/pay?token=secret",
        },
      }),
    ),
  ], {
    allowedRedirectHosts: ["cardpayment.flexpay.cd"],
  });
  modal.mount();
  await modal.open();
  dom.window.document.querySelector('[data-payment-method="card"]').click();
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.match(
    dom.window.document.querySelector(".flexpay-checkout-status-text").textContent,
    /portail de paiement sécurisé/,
  );
  modal.destroy();
});

test("la langue anglaise et les textes personnalisés sont appliqués", async () => {
  const { dom, modal } = modalWithResponses([jsonResponse(fixture())], {
    locale: "en",
    messages: { card: "Secure card" },
  });
  modal.mount();
  await modal.open();
  const labels = Array.from(
    dom.window.document.querySelectorAll(".flexpay-checkout-method"),
  ).map((node) => node.textContent);
  assert.deepEqual(labels, ["Mobile Money", "Secure card"]);
  modal.destroy();
});

test("Échap ferme uniquement lorsque la confirmation pending l’autorise", async () => {
  const { dom, modal } = modalWithResponses([
    jsonResponse(fixture({ status: "pending", selected_method: "mobile_money" })),
  ], {
    confirmClose: () => false,
  });
  modal.mount();
  await modal.open();
  dom.window.document.dispatchEvent(
    new dom.window.KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
  );
  assert.equal(modal.root.hidden, false);
  modal.destroy();
});

test("les événements ouverts et fermés restent filtrés", async () => {
  const { modal } = modalWithResponses([jsonResponse(fixture())]);
  let opened;
  let closed;
  modal.addEventListener(CHECKOUT_EVENTS.opened, (event) => (opened = event.detail));
  modal.addEventListener(CHECKOUT_EVENTS.closed, (event) => (closed = event.detail));
  modal.mount();
  await modal.open();
  modal.close(true);
  assert.equal(opened.checkoutId, "chk_modal");
  assert.equal(closed.checkoutId, "chk_modal");
  assert.equal("raw_response" in closed, false);
  modal.destroy();
});
