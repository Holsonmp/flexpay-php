import test from "node:test";
import assert from "node:assert/strict";

import {
  CHECKOUT_EVENTS,
  FlexPayCheckoutApi,
  FlexPayCheckoutController,
} from "../../resources/checkout/js/index.js";

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function queuedFetch(responses, calls = []) {
  return async (url, options) => {
    calls.push({ url, options });
    const next = responses.shift();
    if (next instanceof Error) {
      throw next;
    }
    if (typeof next === "function") {
      return next(url, options);
    }
    if (!next) {
      throw new Error("Réponse fetch factice manquante.");
    }
    return next;
  };
}

function controllerOptions(fetch, overrides = {}) {
  return {
    checkoutId: "chk_public_1",
    endpoints: {
      details: "/checkouts/{checkoutId}",
      initialize: "/checkouts/{checkoutId}/initialize",
      status: "/checkouts/{checkoutId}/status",
    },
    fetch,
    autoRedirect: false,
    ...overrides,
  };
}

const details = {
  checkout_id: "chk_public_1",
  merchant_reference: "ORDER-2026-0001",
  amount: 50,
  currency: "USD",
  allowed_methods: ["mobile_money", "card"],
  selected_method: null,
  status: "created",
  checkout_state: "created",
  terminal: false,
};

test("l’API applique le checkoutId, le CSRF et le même origin", async () => {
  const calls = [];
  const api = new FlexPayCheckoutApi({
    ...controllerOptions(queuedFetch([jsonResponse(details)], calls)),
    csrf: { header: "X-CSRF-TOKEN", token: "csrf-test" },
  });

  await api.initialize("mobile_money", "+243970000003");

  assert.equal(calls[0].url, "/checkouts/chk_public_1/initialize");
  assert.equal(calls[0].options.headers["X-CSRF-TOKEN"], "csrf-test");
  assert.equal(calls[0].options.credentials, "same-origin");
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    payment_method: "mobile_money",
    phone_number: "+243970000003",
  });
});

test("le contrôleur charge uniquement les détails publics", async () => {
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([jsonResponse(details)])),
  );
  assert.deepEqual(await controller.load(), details);
  controller.destroy();
});

test("un téléphone vide ou invalide est refusé avant fetch", async () => {
  const calls = [];
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([], calls)),
  );
  await assert.rejects(
    () => controller.initializeMobileMoney(""),
    /Numéro Mobile Money invalide/,
  );
  assert.equal(calls.length, 0);
  controller.destroy();
});

test("un double clic n’envoie qu’une initialisation", async () => {
  const calls = [];
  let resolveFetch;
  const fetch = queuedFetch([
    () =>
      new Promise((resolve) => {
        resolveFetch = resolve;
      }),
  ], calls);
  const controller = new FlexPayCheckoutController(controllerOptions(fetch));

  const first = controller.initializeMobileMoney("+243970000003");
  const second = controller.initializeMobileMoney("+243970000003");
  resolveFetch(
    jsonResponse({
      ...details,
      selected_method: "mobile_money",
      status: "pending",
      checkout_state: "pending",
      action: { type: "poll", after_ms: 4000 },
    }),
  );
  await Promise.all([first, second]);

  assert.equal(calls.length, 1);
  controller.destroy();
});

test("le polling traverse deux pending puis successful et s’arrête", async () => {
  const timers = [];
  const fetch = queuedFetch([
    jsonResponse({ ...details, status: "pending", checkout_state: "pending" }),
    jsonResponse({ ...details, status: "pending", checkout_state: "pending" }),
    jsonResponse({
      ...details,
      status: "successful",
      checkout_state: "successful",
      terminal: true,
    }),
  ]);
  const controller = new FlexPayCheckoutController(
    controllerOptions(fetch, {
      setTimeout: (callback, delay) => {
        timers.push({ callback, delay });
        return timers.length;
      },
      clearTimeout: () => {},
    }),
  );
  controller.current = { ...details, status: "pending" };
  controller.pollStartedAt = Date.now();
  let success = 0;
  controller.addEventListener(CHECKOUT_EVENTS.success, () => success++);

  await controller.pollOnce();
  await controller.pollOnce();
  await controller.pollOnce();

  assert.equal(controller.current.status, "successful");
  assert.equal(success, 1);
  assert.equal(controller.pollTimer, null);
  controller.destroy();
});

test("un statut failed arrête immédiatement le polling", async () => {
  const controller = new FlexPayCheckoutController(
    controllerOptions(
      queuedFetch([
        jsonResponse({ ...details, status: "failed", terminal: true }),
      ]),
    ),
  );
  controller.current = { ...details, status: "pending" };
  controller.pollStartedAt = Date.now();
  await controller.pollOnce();
  assert.equal(controller.current.status, "failed");
  assert.equal(controller.pollTimer, null);
  controller.destroy();
});

test("une erreur réseau temporaire émet error et programme une reprise", async () => {
  const timers = [];
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([new Error("offline")]), {
      setTimeout: (callback, delay) => {
        timers.push({ callback, delay });
        return timers.length;
      },
      clearTimeout: () => {},
    }),
  );
  controller.current = { ...details, status: "pending" };
  controller.pollStartedAt = Date.now();
  let errors = 0;
  controller.addEventListener(CHECKOUT_EVENTS.error, () => errors++);
  await controller.pollOnce();

  assert.equal(errors, 1);
  assert.equal(timers.length, 1);
  assert.equal(controller.current.status, "pending");
  controller.destroy();
});

test("le timeout du polling produit unknown sans fabriquer failed", async () => {
  let now = 1000;
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([]), {
      now: () => now,
      polling: { timeoutMs: 1000 },
    }),
  );
  controller.current = { ...details, status: "pending" };
  controller.pollStartedAt = 1000;
  now = 2000;
  let unknown;
  controller.addEventListener(CHECKOUT_EVENTS.unknown, (event) => {
    unknown = event.detail;
  });

  await controller.pollOnce();

  assert.equal(unknown.terminal, false);
  assert.notEqual(unknown.status, "failed");
  controller.destroy();
});

test("un onglet masqué suspend le polling puis le reprend", () => {
  const listeners = new Map();
  const fakeDocument = {
    hidden: true,
    addEventListener: (name, callback) => listeners.set(name, callback),
    removeEventListener: (name) => listeners.delete(name),
  };
  const timers = [];
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([]), {
      document: fakeDocument,
      setTimeout: (callback, delay) => {
        timers.push({ callback, delay });
        return timers.length;
      },
      clearTimeout: () => {},
    }),
  );
  controller.current = { ...details, status: "pending" };
  controller.startPolling();
  assert.equal(timers.length, 0);

  fakeDocument.hidden = false;
  listeners.get("visibilitychange")();
  assert.equal(timers.length, 1);
  controller.destroy();
});

test("la redirection carte est contrôlée et peut être injectée", async () => {
  let redirectedTo = "";
  const controller = new FlexPayCheckoutController(
    controllerOptions(
      queuedFetch([
        jsonResponse({
          ...details,
          selected_method: "card",
          status: "requires_action",
          action: {
            type: "redirect",
            redirect_url: "https://cardpayment.flexpay.cd/v3/pay?token=secret",
          },
        }),
      ]),
      {
        autoRedirect: true,
        allowedRedirectHosts: ["cardpayment.flexpay.cd"],
        navigate: (url) => {
          redirectedTo = url;
        },
      },
    ),
  );
  await controller.initializeCard();
  assert.match(redirectedTo, /^https:\/\/cardpayment\.flexpay\.cd/);
  controller.destroy();
});

test("les schémas et hôtes de redirection dangereux sont refusés", () => {
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([]), {
      allowedRedirectHosts: ["cardpayment.flexpay.cd"],
    }),
  );
  assert.throws(() => controller.validateRedirectUrl("javascript:alert(1)"));
  assert.throws(() => controller.validateRedirectUrl("https://evil.example/pay"));
  controller.destroy();
});

test("les événements ne contiennent ni téléphone ni payload brut", async () => {
  const payload = {
    ...details,
    selected_method: "mobile_money",
    status: "pending",
    phone_number: "+243970000003",
    raw_response: { token: "secret" },
    action: { type: "poll", after_ms: 4000 },
  };
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([jsonResponse(payload)])),
  );
  let detail;
  controller.addEventListener(CHECKOUT_EVENTS.pending, (event) => {
    detail = event.detail;
  });
  await controller.initializeMobileMoney("+243970000003");
  assert.equal(detail.paymentMethod, "mobile_money");
  assert.equal("phone_number" in detail, false);
  assert.equal("raw_response" in detail, false);
  controller.destroy();
});

test("destroy arrête les timers et interdit une réutilisation", async () => {
  const controller = new FlexPayCheckoutController(
    controllerOptions(queuedFetch([])),
  );
  controller.destroy();
  assert.throws(() => controller.resumePolling(), /détruite/);
  await assert.rejects(() => controller.load(), /détruite/);
});
