import { FlexPayCheckoutApi } from "./checkout-api.js";
import {
  CHECKOUT_EVENTS,
  checkoutEvent,
  publicEventDetail,
} from "./checkout-events.js";

const TERMINAL_STATUSES = new Set([
  "successful",
  "failed",
  "cancelled",
  "expired",
]);

export class FlexPayCheckoutController extends EventTarget {
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
    this.allowedRedirectHosts = (options.allowedRedirectHosts || []).map((host) =>
      String(host).toLowerCase(),
    );
    this.polling = {
      initialDelayMs: Math.max(0, Number(options.polling?.initialDelayMs ?? 0)),
      intervalMs: Math.max(250, Number(options.polling?.intervalMs ?? 4000)),
      maximumIntervalMs: Math.max(
        250,
        Number(options.polling?.maximumIntervalMs ?? 10000),
      ),
      timeoutMs: Math.max(1000, Number(options.polling?.timeoutMs ?? 180000)),
      maximumNetworkErrors: Math.max(
        1,
        Number(options.polling?.maximumNetworkErrors ?? 3),
      ),
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
      throw new TypeError("Ce moyen de paiement n’est pas autorisé.");
    }
    this.current = { ...this.current, selected_method: method };
    this.emit(CHECKOUT_EVENTS.methodSelected, this.current);
  }

  async initializeMobileMoney(phoneNumber) {
    this.assertActive();
    const phone = String(phoneNumber || "").trim();
    if (!/^\+?[0-9 ()-]{7,24}$/.test(phone)) {
      const error = new TypeError("Numéro Mobile Money invalide.");
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
      ...(this.current || {}),
      selected_method: paymentMethod,
      checkout_state: "initializing",
    });

    const payload = await this.withRequest((signal) =>
      this.api.initialize(paymentMethod, phoneNumber, { signal }),
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
      ...(this.current || {}),
      terminal: false,
      message: "Vérification locale interrompue.",
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
        ...(this.current || {}),
        checkout_state: "unknown",
        terminal: false,
      });
      return this.current;
    }

    this.statusInFlight = true;
    this.emit(CHECKOUT_EVENTS.verifying, {
      ...(this.current || {}),
      checkout_state: "verifying",
    });
    try {
      const payload = await this.api.status({ signal: this.newSignal() });
      this.current = payload;
      this.pollErrors = 0;
      this.processPayload(payload);
      if (!payload.terminal && !TERMINAL_STATUSES.has(payload.status)) {
        this.schedulePoll(
          Number(payload.next_poll_after_ms ?? this.polling.intervalMs),
        );
      }
      return payload;
    } catch (error) {
      if (error?.name === "AbortError") {
        return this.current;
      }
      this.pollErrors += 1;
      this.emit(CHECKOUT_EVENTS.error, {
        ...(this.current || {}),
        message: error?.publicMessage || error?.message || "Erreur réseau.",
      });
      if (this.pollErrors >= this.polling.maximumNetworkErrors) {
        this.stopPolling();
        this.emit(CHECKOUT_EVENTS.unknown, {
          ...(this.current || {}),
          checkout_state: "unknown",
          terminal: false,
        });
        return this.current;
      }
      const delay = Math.min(
        this.polling.maximumIntervalMs,
        this.polling.intervalMs * 2 ** Math.min(this.pollErrors, 8),
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
      unknown: CHECKOUT_EVENTS.unknown,
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
      throw new TypeError("L’adresse de redirection du paiement est invalide.");
    }
    if (url.protocol !== "https:") {
      throw new TypeError("L’adresse de redirection doit utiliser HTTPS.");
    }
    if (
      this.allowedRedirectHosts.length > 0 &&
      !this.allowedRedirectHosts.includes(url.hostname.toLowerCase())
    ) {
      throw new TypeError("L’hôte de redirection du paiement n’est pas autorisé.");
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
      throw new Error("Cette instance FlexPay Checkout a été détruite.");
    }
  }
}
