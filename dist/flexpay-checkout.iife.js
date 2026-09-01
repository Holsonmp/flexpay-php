/*! FlexPay Intégration By Holsonmp - Holduix | Copyright (c) 2026 Holson Mpangala / Holduix | MIT */
var FlexPayCheckout = (() => {
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // resources/checkout/js/index.js
  var index_exports = {};
  __export(index_exports, {
    CHECKOUT_EVENTS: () => CHECKOUT_EVENTS,
    Controller: () => FlexPayCheckoutController,
    FLEXPAY_CHECKOUT_MESSAGES: () => FLEXPAY_CHECKOUT_MESSAGES,
    FlexPayCheckoutApi: () => FlexPayCheckoutApi,
    FlexPayCheckoutController: () => FlexPayCheckoutController,
    FlexPayCheckoutHttpError: () => FlexPayCheckoutHttpError,
    FlexPayCheckoutModal: () => FlexPayCheckoutModal,
    Modal: () => FlexPayCheckoutModal,
    checkoutMessages: () => checkoutMessages
  });

  // resources/checkout/js/checkout-api.js
  var FlexPayCheckoutHttpError = class extends Error {
    constructor(message, status = 0, payload = {}) {
      super(message);
      this.name = "FlexPayCheckoutHttpError";
      this.status = status;
      this.code = payload?.error?.code || "checkout_http_error";
      this.publicMessage = payload?.error?.message || payload?.message || message;
      this.payload = payload;
    }
  };
  var FlexPayCheckoutApi = class {
    constructor(options) {
      if (!options?.checkoutId) {
        throw new TypeError("FlexPay Checkout : checkoutId est obligatoire.");
      }
      if (typeof (options.fetch || globalThis.fetch) !== "function") {
        throw new TypeError("FlexPay Checkout : une impl\xE9mentation de fetch est obligatoire.");
      }
      this.checkoutId = String(options.checkoutId);
      this.endpoints = {
        details: options.endpoints?.details || "/payments/flexpay/checkouts/{checkoutId}",
        initialize: options.endpoints?.initialize || "/payments/flexpay/checkouts/{checkoutId}/initialize",
        status: options.endpoints?.status || "/payments/flexpay/checkouts/{checkoutId}/status"
      };
      this.csrf = options.csrf || {};
      this.fetch = options.fetch || globalThis.fetch.bind(globalThis);
      this.credentials = options.credentials || "same-origin";
    }
    details({ signal } = {}) {
      return this.request("GET", this.endpoints.details, null, signal);
    }
    initialize(paymentMethod, phoneNumber = "", { signal } = {}) {
      const payload = { payment_method: paymentMethod };
      if (paymentMethod === "mobile_money") {
        payload.phone_number = phoneNumber;
      }
      return this.request("POST", this.endpoints.initialize, payload, signal);
    }
    status({ signal } = {}) {
      return this.request("GET", this.endpoints.status, null, signal);
    }
    async request(method, endpointTemplate, payload, signal) {
      const headers = { Accept: "application/json" };
      if (payload !== null) {
        headers["Content-Type"] = "application/json";
      }
      if (this.csrf.header && this.csrf.token) {
        headers[String(this.csrf.header)] = String(this.csrf.token);
      }
      let response;
      try {
        response = await this.fetch(this.resolveEndpoint(endpointTemplate), {
          method,
          headers,
          credentials: this.credentials,
          body: payload === null ? void 0 : JSON.stringify(payload),
          signal
        });
      } catch (error) {
        if (error?.name === "AbortError") {
          throw error;
        }
        throw new FlexPayCheckoutHttpError(
          "Le serveur de paiement est momentan\xE9ment inaccessible.",
          0
        );
      }
      let data = {};
      try {
        data = await response.json();
      } catch (_error) {
        throw new FlexPayCheckoutHttpError(
          "La r\xE9ponse du serveur de paiement est illisible.",
          response.status
        );
      }
      if (!response.ok) {
        throw new FlexPayCheckoutHttpError(
          data?.message || "La requ\xEAte de paiement a \xE9chou\xE9.",
          response.status,
          data
        );
      }
      return data;
    }
    resolveEndpoint(template) {
      return String(template).split("{checkoutId}").join(encodeURIComponent(this.checkoutId));
    }
  };

  // resources/checkout/js/checkout-events.js
  var CHECKOUT_EVENTS = Object.freeze({
    opened: "flexpay:opened",
    closed: "flexpay:closed",
    methodSelected: "flexpay:method-selected",
    initializing: "flexpay:initializing",
    pending: "flexpay:pending",
    redirecting: "flexpay:redirecting",
    verifying: "flexpay:verifying",
    success: "flexpay:success",
    failed: "flexpay:failed",
    cancelled: "flexpay:cancelled",
    expired: "flexpay:expired",
    unknown: "flexpay:unknown",
    error: "flexpay:error"
  });
  function publicEventDetail(payload = {}) {
    return {
      checkoutId: String(payload.checkout_id || payload.checkoutId || ""),
      merchantReference: String(
        payload.merchant_reference || payload.merchantReference || ""
      ),
      paymentMethod: payload.selected_method || payload.paymentMethod || null,
      status: String(payload.status || "unknown"),
      checkoutState: String(
        payload.checkout_state || payload.checkoutState || payload.status || "unknown"
      ),
      terminal: payload.terminal === true,
      message: String(payload.message || "")
    };
  }
  function checkoutEvent(name, detail) {
    if (typeof CustomEvent === "function") {
      return new CustomEvent(name, { detail });
    }
    const event = new Event(name);
    Object.defineProperty(event, "detail", { value: detail, enumerable: true });
    return event;
  }

  // resources/checkout/js/checkout-controller.js
  var TERMINAL_STATUSES = /* @__PURE__ */ new Set([
    "successful",
    "failed",
    "cancelled",
    "expired"
  ]);
  var FlexPayCheckoutController = class extends EventTarget {
    constructor(options = {}) {
      super();
      this.options = options;
      this.api = options.api || new FlexPayCheckoutApi(options);
      this.checkoutId = String(options.checkoutId || "");
      this.document = options.document || globalThis.document || null;
      this.now = options.now || (() => Date.now());
      this.setTimer = options.setTimeout || globalThis.setTimeout.bind(globalThis);
      this.clearTimer = options.clearTimeout || globalThis.clearTimeout.bind(globalThis);
      this.navigate = options.navigate || ((url) => globalThis.location.assign(url));
      this.allowedRedirectHosts = (options.allowedRedirectHosts || []).map(
        (host) => String(host).toLowerCase()
      );
      if (this.allowedRedirectHosts.length === 0 || this.allowedRedirectHosts.some((host) => !/^[a-z0-9.-]+$/i.test(host))) {
        throw new TypeError("Au moins un h\xF4te de redirection doit \xEAtre explicitement autoris\xE9.");
      }
      this.polling = {
        initialDelayMs: Math.max(0, Number(options.polling?.initialDelayMs ?? 0)),
        intervalMs: Math.max(250, Number(options.polling?.intervalMs ?? 4e3)),
        maximumIntervalMs: Math.max(
          250,
          Number(options.polling?.maximumIntervalMs ?? 1e4)
        ),
        timeoutMs: Math.max(1e3, Number(options.polling?.timeoutMs ?? 18e4)),
        maximumNetworkErrors: Math.max(
          1,
          Number(options.polling?.maximumNetworkErrors ?? 3)
        )
      };
      this.autoRedirect = options.autoRedirect !== false;
      this.current = null;
      this.destroyed = false;
      this.busy = false;
      this.pollTimer = null;
      this.pollStartedAt = 0;
      this.pollErrors = 0;
      this.statusInFlight = false;
      this.abortController = null;
      this.visibilityHandler = () => this.handleVisibilityChange();
      this.document?.addEventListener?.("visibilitychange", this.visibilityHandler);
    }
    async load() {
      this.assertActive();
      const payload = await this.withRequest((signal) => this.api.details({ signal }));
      this.current = payload;
      return payload;
    }
    selectPaymentMethod(paymentMethod) {
      const method = String(paymentMethod);
      if (!this.current?.allowed_methods?.includes(method)) {
        throw new TypeError("Ce moyen de paiement n\u2019est pas autoris\xE9.");
      }
      this.current = { ...this.current, selected_method: method };
      this.emit(CHECKOUT_EVENTS.methodSelected, this.current);
    }
    async initializeMobileMoney(phoneNumber) {
      this.assertActive();
      const phone = String(phoneNumber || "").trim();
      if (!/^\+?[0-9 ()-]{7,24}$/.test(phone)) {
        const error = new TypeError("Num\xE9ro Mobile Money invalide.");
        error.code = "invalid_phone_number";
        throw error;
      }
      return this.initialize("mobile_money", phone);
    }
    initializeCard() {
      this.assertActive();
      return this.initialize("card");
    }
    async initialize(paymentMethod, phoneNumber = "") {
      if (this.busy) {
        return this.current;
      }
      this.emit(CHECKOUT_EVENTS.initializing, {
        ...this.current || {},
        selected_method: paymentMethod,
        checkout_state: "initializing"
      });
      const payload = await this.withRequest(
        (signal) => this.api.initialize(paymentMethod, phoneNumber, { signal })
      );
      this.current = payload;
      this.processPayload(payload);
      const action = payload.action || {};
      if (action.type === "poll" && !payload.terminal) {
        this.startPolling(Number(action.after_ms ?? this.polling.initialDelayMs));
      }
      if (action.type === "redirect") {
        const url = this.validateRedirectUrl(action.redirect_url);
        this.emit(CHECKOUT_EVENTS.redirecting, payload);
        if (this.autoRedirect) {
          this.navigate(url);
        }
      }
      return payload;
    }
    startPolling(initialDelayMs = this.polling.initialDelayMs) {
      this.assertActive();
      this.stopPolling();
      this.pollStartedAt = this.now();
      this.pollErrors = 0;
      this.schedulePoll(Math.max(0, initialDelayMs));
    }
    resumePolling() {
      this.assertActive();
      if (this.current?.terminal || TERMINAL_STATUSES.has(this.current?.status)) {
        return;
      }
      if (!this.pollStartedAt) {
        this.pollStartedAt = this.now();
      }
      if (!this.pollTimer && !this.statusInFlight && !this.document?.hidden) {
        this.schedulePoll(0);
      }
    }
    stopPolling() {
      if (this.pollTimer !== null) {
        this.clearTimer(this.pollTimer);
        this.pollTimer = null;
      }
    }
    cancelLocal() {
      this.stopPolling();
      this.emit(CHECKOUT_EVENTS.cancelled, {
        ...this.current || {},
        terminal: false,
        message: "V\xE9rification locale interrompue."
      });
    }
    destroy() {
      if (this.destroyed) {
        return;
      }
      this.stopPolling();
      this.abortController?.abort();
      this.document?.removeEventListener?.("visibilitychange", this.visibilityHandler);
      this.destroyed = true;
    }
    async pollOnce() {
      if (this.destroyed || this.statusInFlight || this.document?.hidden) {
        return this.current;
      }
      if (this.now() - this.pollStartedAt >= this.polling.timeoutMs) {
        this.stopPolling();
        this.emit(CHECKOUT_EVENTS.unknown, {
          ...this.current || {},
          checkout_state: "unknown",
          terminal: false
        });
        return this.current;
      }
      this.statusInFlight = true;
      this.emit(CHECKOUT_EVENTS.verifying, {
        ...this.current || {},
        checkout_state: "verifying"
      });
      try {
        const payload = await this.api.status({ signal: this.newSignal() });
        this.current = payload;
        this.pollErrors = 0;
        this.processPayload(payload);
        if (!payload.terminal && !TERMINAL_STATUSES.has(payload.status)) {
          this.schedulePoll(
            Number(payload.next_poll_after_ms ?? this.polling.intervalMs)
          );
        }
        return payload;
      } catch (error) {
        if (error?.name === "AbortError") {
          return this.current;
        }
        this.pollErrors += 1;
        this.emit(CHECKOUT_EVENTS.error, {
          ...this.current || {},
          message: error?.publicMessage || error?.message || "Erreur r\xE9seau."
        });
        if (this.pollErrors >= this.polling.maximumNetworkErrors) {
          this.stopPolling();
          this.emit(CHECKOUT_EVENTS.unknown, {
            ...this.current || {},
            checkout_state: "unknown",
            terminal: false
          });
          return this.current;
        }
        const delay = Math.min(
          this.polling.maximumIntervalMs,
          this.polling.intervalMs * 2 ** Math.min(this.pollErrors, 8)
        );
        this.schedulePoll(delay);
        return this.current;
      } finally {
        this.statusInFlight = false;
      }
    }
    processPayload(payload) {
      const status = String(payload?.status || "unknown");
      const eventName = {
        pending: CHECKOUT_EVENTS.pending,
        processing: CHECKOUT_EVENTS.pending,
        requires_action: CHECKOUT_EVENTS.pending,
        successful: CHECKOUT_EVENTS.success,
        failed: CHECKOUT_EVENTS.failed,
        cancelled: CHECKOUT_EVENTS.cancelled,
        expired: CHECKOUT_EVENTS.expired,
        unknown: CHECKOUT_EVENTS.unknown
      }[status];
      if (payload?.terminal || TERMINAL_STATUSES.has(status)) {
        this.stopPolling();
      }
      if (eventName) {
        this.emit(eventName, payload);
      }
    }
    validateRedirectUrl(value) {
      let url;
      try {
        url = new URL(String(value || ""));
      } catch (_error) {
        throw new TypeError("L\u2019adresse de redirection du paiement est invalide.");
      }
      if (url.protocol !== "https:") {
        throw new TypeError("L\u2019adresse de redirection doit utiliser HTTPS.");
      }
      if (!this.allowedRedirectHosts.includes(url.hostname.toLowerCase())) {
        throw new TypeError("L\u2019h\xF4te de redirection du paiement n\u2019est pas autoris\xE9.");
      }
      return url.toString();
    }
    schedulePoll(delayMs) {
      this.stopPolling();
      if (this.destroyed || this.document?.hidden) {
        return;
      }
      this.pollTimer = this.setTimer(() => {
        this.pollTimer = null;
        void this.pollOnce();
      }, Math.max(0, delayMs));
    }
    handleVisibilityChange() {
      if (this.document?.hidden) {
        this.stopPolling();
        return;
      }
      this.resumePolling();
    }
    async withRequest(callback) {
      if (this.busy) {
        return this.current;
      }
      this.busy = true;
      try {
        return await callback(this.newSignal());
      } finally {
        this.busy = false;
      }
    }
    newSignal() {
      this.abortController = new AbortController();
      return this.abortController.signal;
    }
    emit(name, payload) {
      this.dispatchEvent(checkoutEvent(name, publicEventDetail(payload)));
    }
    assertActive() {
      if (this.destroyed) {
        throw new Error("Cette instance FlexPay Checkout a \xE9t\xE9 d\xE9truite.");
      }
    }
  };

  // resources/checkout/js/checkout-i18n.js
  var FLEXPAY_CHECKOUT_MESSAGES = Object.freeze({
    fr: Object.freeze({
      choosePaymentMethod: "Choisissez votre moyen de paiement",
      mobileMoney: "Mobile Money",
      card: "Carte bancaire",
      phoneNumber: "Num\xE9ro Mobile Money",
      phonePlaceholder: "+243\u2026",
      continue: "Continuer",
      back: "Retour",
      close: "Fermer",
      initializing: "Initialisation du paiement\u2026",
      confirmOnPhone: "Confirmez la demande sur votre t\xE9l\xE9phone.",
      waitingForConfirmation: "Confirmation du paiement en attente.",
      redirectingToSecurePortal: "Redirection vers le portail de paiement s\xE9curis\xE9\u2026",
      verifyingPayment: "V\xE9rification du paiement\u2026",
      paymentSuccessful: "Votre paiement est confirm\xE9.",
      paymentFailed: "Le paiement n\u2019a pas \xE9t\xE9 confirm\xE9.",
      paymentCancelled: "Le paiement a \xE9t\xE9 annul\xE9.",
      paymentExpired: "La session de paiement a expir\xE9.",
      verificationTakingLonger: "La confirmation prend plus de temps que pr\xE9vu. Votre paiement n\u2019est pas d\xE9clar\xE9 en \xE9chec. Vous pouvez reprendre la v\xE9rification avec la m\xEAme r\xE9f\xE9rence.",
      retryVerification: "Reprendre la v\xE9rification",
      invalidPhoneNumber: "Saisissez un num\xE9ro valide au format international.",
      networkError: "La connexion a \xE9t\xE9 interrompue. Une nouvelle v\xE9rification sera tent\xE9e.",
      closePendingConfirmation: "Une confirmation est encore attendue. Fermer la fen\xEAtre interrompra uniquement l\u2019affichage de la v\xE9rification."
    }),
    en: Object.freeze({
      choosePaymentMethod: "Choose your payment method",
      mobileMoney: "Mobile Money",
      card: "Payment card",
      phoneNumber: "Mobile Money number",
      phonePlaceholder: "+243\u2026",
      continue: "Continue",
      back: "Back",
      close: "Close",
      initializing: "Initializing payment\u2026",
      confirmOnPhone: "Confirm the payment request on your phone.",
      waitingForConfirmation: "Waiting for payment confirmation.",
      redirectingToSecurePortal: "Redirecting to the secure payment portal\u2026",
      verifyingPayment: "Verifying payment\u2026",
      paymentSuccessful: "Your payment is confirmed.",
      paymentFailed: "The payment could not be confirmed.",
      paymentCancelled: "The payment was cancelled.",
      paymentExpired: "The payment session has expired.",
      verificationTakingLonger: "Confirmation is taking longer than expected. Your payment has not been marked as failed. You can resume verification with the same reference.",
      retryVerification: "Resume verification",
      invalidPhoneNumber: "Enter a valid international phone number.",
      networkError: "The connection was interrupted. Verification will be retried.",
      closePendingConfirmation: "Confirmation is still pending. Closing this window only stops the on-screen verification."
    })
  });
  function checkoutMessages(locale = "fr", overrides = {}) {
    const base = FLEXPAY_CHECKOUT_MESSAGES[locale] || FLEXPAY_CHECKOUT_MESSAGES.fr;
    return { ...base, ...overrides };
  }

  // resources/checkout/js/checkout-modal.js
  var FlexPayCheckoutModal = class extends EventTarget {
    constructor(options = {}) {
      super();
      this.options = options;
      this.document = options.document || globalThis.document;
      if (!this.document) {
        throw new TypeError("FlexPay Checkout Modal n\xE9cessite un document DOM.");
      }
      this.messages = checkoutMessages(options.locale || "fr", options.messages || {});
      this.controller = options.controller || new FlexPayCheckoutController({ ...options, document: this.document });
      this.confirmClose = options.confirmClose || ((message) => globalThis.confirm?.(message) !== false);
      this.trigger = null;
      this.root = null;
      this.dialog = null;
      this.body = null;
      this.live = null;
      this.closeButton = null;
      this.lastFocused = null;
      this.pending = false;
      this.mounted = false;
      this.triggerHandler = (event) => {
        event.preventDefault();
        void this.open();
      };
      this.keyHandler = (event) => this.handleKeydown(event);
      this.bindControllerEvents();
    }
    mount() {
      if (this.mounted) {
        return this;
      }
      this.trigger = typeof this.options.trigger === "string" ? this.document.querySelector(this.options.trigger) : this.options.trigger;
      if (!this.trigger) {
        throw new TypeError("FlexPay Checkout Modal : bouton d\xE9clencheur introuvable.");
      }
      this.trigger.addEventListener("click", this.triggerHandler);
      this.mounted = true;
      return this;
    }
    async open() {
      if (!this.mounted) {
        this.mount();
      }
      this.ensureDom();
      this.lastFocused = this.document.activeElement;
      this.root.hidden = false;
      this.document.addEventListener("keydown", this.keyHandler);
      this.renderStatus(this.messages.initializing, true);
      this.closeButton.focus();
      this.emit(CHECKOUT_EVENTS.opened, this.controller.current || {
        checkout_id: this.controller.checkoutId,
        status: "created",
        checkout_state: "created",
        terminal: false
      });
      try {
        const details = await this.controller.load();
        if (!details.terminal && ["pending", "processing", "unknown"].includes(String(details.status))) {
          this.pending = true;
          this.renderPending(details);
          this.controller.resumePolling();
        } else {
          this.pending = false;
          this.renderMethods(details);
        }
      } catch (error) {
        this.renderError(error?.publicMessage || error?.message || this.messages.networkError);
      }
    }
    close(force = false) {
      if (!this.root || this.root.hidden) {
        return false;
      }
      if (!force && this.pending && !this.confirmClose(this.messages.closePendingConfirmation)) {
        return false;
      }
      this.controller.stopPolling();
      this.root.hidden = true;
      this.document.removeEventListener("keydown", this.keyHandler);
      this.lastFocused?.focus?.();
      this.emit(CHECKOUT_EVENTS.closed, this.controller.current || {});
      return true;
    }
    destroy() {
      this.trigger?.removeEventListener("click", this.triggerHandler);
      this.document.removeEventListener("keydown", this.keyHandler);
      this.controller.destroy();
      this.root?.remove();
      this.root = null;
      this.mounted = false;
    }
    ensureDom() {
      if (this.root) {
        return;
      }
      const root = this.document.createElement("div");
      root.className = "flexpay-checkout-overlay";
      root.hidden = true;
      root.innerHTML = `
      <section class="flexpay-checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="flexpay-checkout-title">
        <header class="flexpay-checkout-header">
          <h2 id="flexpay-checkout-title" class="flexpay-checkout-title"></h2>
          <button type="button" class="flexpay-checkout-close" aria-label=""></button>
        </header>
        <div class="flexpay-checkout-live" role="status" aria-live="polite"></div>
        <div class="flexpay-checkout-body"></div>
      </section>`;
      this.document.body.append(root);
      this.root = root;
      this.dialog = root.querySelector(".flexpay-checkout-dialog");
      this.body = root.querySelector(".flexpay-checkout-body");
      this.live = root.querySelector(".flexpay-checkout-live");
      this.closeButton = root.querySelector(".flexpay-checkout-close");
      root.querySelector(".flexpay-checkout-title").textContent = this.messages.choosePaymentMethod;
      this.closeButton.textContent = "\xD7";
      this.closeButton.setAttribute("aria-label", this.messages.close);
      this.closeButton.addEventListener("click", () => this.close());
      root.addEventListener("mousedown", (event) => {
        if (event.target === root) {
          this.close();
        }
      });
    }
    renderMethods(details) {
      this.live.textContent = this.messages.choosePaymentMethod;
      this.body.replaceChildren();
      const methods = this.document.createElement("div");
      methods.className = "flexpay-checkout-methods";
      for (const method of details.allowed_methods || []) {
        if (!["mobile_money", "card"].includes(method)) {
          continue;
        }
        const button = this.button(
          method === "mobile_money" ? this.messages.mobileMoney : this.messages.card,
          "flexpay-checkout-method"
        );
        button.dataset.paymentMethod = method;
        button.addEventListener("click", () => {
          this.controller.selectPaymentMethod(method);
          if (method === "mobile_money") {
            this.renderPhone();
          } else {
            void this.initializeCard(button);
          }
        });
        methods.append(button);
      }
      this.body.append(methods);
      methods.querySelector("button")?.focus();
    }
    renderPhone() {
      this.live.textContent = this.messages.phoneNumber;
      this.body.replaceChildren();
      const form = this.document.createElement("form");
      form.className = "flexpay-checkout-form";
      form.noValidate = true;
      const label = this.document.createElement("label");
      label.className = "flexpay-checkout-label";
      label.htmlFor = "flexpay-checkout-phone";
      label.textContent = this.messages.phoneNumber;
      const input = this.document.createElement("input");
      input.id = "flexpay-checkout-phone";
      input.className = "flexpay-checkout-input";
      input.type = "tel";
      input.inputMode = "tel";
      input.autocomplete = "tel";
      input.placeholder = this.messages.phonePlaceholder;
      input.setAttribute("aria-describedby", "flexpay-checkout-phone-error");
      const error = this.document.createElement("p");
      error.id = "flexpay-checkout-phone-error";
      error.className = "flexpay-checkout-field-error";
      const actions = this.document.createElement("div");
      actions.className = "flexpay-checkout-actions";
      const back = this.button(this.messages.back, "flexpay-checkout-button-secondary");
      const submit = this.button(this.messages.continue, "flexpay-checkout-button-primary");
      submit.type = "submit";
      back.addEventListener("click", () => this.renderMethods(this.controller.current));
      actions.append(back, submit);
      form.append(label, input, error, actions);
      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!input.value.trim()) {
          error.textContent = this.messages.invalidPhoneNumber;
          this.live.textContent = this.messages.invalidPhoneNumber;
          input.setAttribute("aria-invalid", "true");
          input.focus();
          return;
        }
        error.textContent = "";
        input.removeAttribute("aria-invalid");
        submit.disabled = true;
        back.disabled = true;
        this.renderStatus(this.messages.initializing, true);
        try {
          await this.controller.initializeMobileMoney(input.value);
        } catch (requestError) {
          submit.disabled = false;
          back.disabled = false;
          const message = requestError?.code === "invalid_phone_number" ? this.messages.invalidPhoneNumber : requestError?.publicMessage || requestError?.message || this.messages.networkError;
          error.textContent = message;
          this.live.textContent = message;
          input.setAttribute("aria-invalid", "true");
        }
      });
      this.body.append(form);
      input.focus();
    }
    async initializeCard(button) {
      button.disabled = true;
      this.renderStatus(this.messages.initializing, true);
      try {
        await this.controller.initializeCard();
      } catch (error) {
        button.disabled = false;
        this.renderError(error?.publicMessage || error?.message || this.messages.networkError);
      }
    }
    renderPending(payload) {
      this.pending = true;
      const message = (payload?.selected_method || payload?.paymentMethod) === "mobile_money" ? this.messages.confirmOnPhone : this.messages.waitingForConfirmation;
      this.renderStatus(message, true);
    }
    renderTerminal(message, kind) {
      this.pending = false;
      this.renderStatus(message, false, kind);
      const close = this.button(this.messages.close, "flexpay-checkout-button-primary");
      close.addEventListener("click", () => this.close(true));
      this.body.append(close);
      close.focus();
    }
    renderUnknown() {
      this.pending = true;
      this.renderStatus(this.messages.verificationTakingLonger, false, "unknown");
      const retry = this.button(
        this.messages.retryVerification,
        "flexpay-checkout-button-primary"
      );
      retry.addEventListener("click", () => {
        retry.disabled = true;
        this.controller.startPolling(0);
      });
      this.body.append(retry);
    }
    renderError(message) {
      this.renderStatus(message, false, "error");
    }
    renderStatus(message, busy = false, kind = "info") {
      this.body.replaceChildren();
      const status = this.document.createElement("div");
      status.className = `flexpay-checkout-status flexpay-checkout-status-${kind}`;
      if (busy) {
        const loader = this.document.createElement("span");
        loader.className = "flexpay-checkout-loader";
        loader.setAttribute("aria-hidden", "true");
        status.append(loader);
      }
      const text = this.document.createElement("p");
      text.className = "flexpay-checkout-status-text";
      text.textContent = message;
      status.append(text);
      this.body.append(status);
      this.live.textContent = message;
    }
    button(label, className) {
      const button = this.document.createElement("button");
      button.type = "button";
      button.className = `flexpay-checkout-button ${className}`;
      button.textContent = label;
      return button;
    }
    handleKeydown(event) {
      if (!this.root || this.root.hidden) {
        return;
      }
      if (event.key === "Escape") {
        event.preventDefault();
        this.close();
        return;
      }
      if (event.key !== "Tab") {
        return;
      }
      const focusable = Array.from(
        this.dialog.querySelectorAll(
          'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
        )
      );
      if (focusable.length === 0) {
        event.preventDefault();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && this.document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && this.document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
    bindControllerEvents() {
      const forward = (name, callback) => {
        this.controller.addEventListener(name, (event) => {
          callback?.(event.detail);
          this.dispatchEvent(checkoutEvent(name, event.detail));
        });
      };
      forward(CHECKOUT_EVENTS.methodSelected);
      forward(
        CHECKOUT_EVENTS.initializing,
        () => this.renderStatus(this.messages.initializing, true)
      );
      forward(CHECKOUT_EVENTS.pending, (detail) => this.renderPending(detail));
      forward(
        CHECKOUT_EVENTS.redirecting,
        () => this.renderStatus(this.messages.redirectingToSecurePortal, true)
      );
      forward(
        CHECKOUT_EVENTS.verifying,
        () => this.renderStatus(this.messages.verifyingPayment, true)
      );
      forward(
        CHECKOUT_EVENTS.success,
        () => this.renderTerminal(this.messages.paymentSuccessful, "success")
      );
      forward(
        CHECKOUT_EVENTS.failed,
        () => this.renderTerminal(this.messages.paymentFailed, "error")
      );
      forward(
        CHECKOUT_EVENTS.cancelled,
        () => this.renderTerminal(this.messages.paymentCancelled, "error")
      );
      forward(
        CHECKOUT_EVENTS.expired,
        () => this.renderTerminal(this.messages.paymentExpired, "error")
      );
      forward(CHECKOUT_EVENTS.unknown, () => this.renderUnknown());
      forward(
        CHECKOUT_EVENTS.error,
        (detail) => this.renderError(detail.message || this.messages.networkError)
      );
    }
    emit(name, payload) {
      this.dispatchEvent(checkoutEvent(name, publicEventDetail(payload)));
    }
  };
  return __toCommonJS(index_exports);
})();
