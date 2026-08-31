import { FlexPayCheckoutController } from "./checkout-controller.js";
import {
  CHECKOUT_EVENTS,
  checkoutEvent,
  publicEventDetail,
} from "./checkout-events.js";
import { checkoutMessages } from "./checkout-i18n.js";

export class FlexPayCheckoutModal extends EventTarget {
  constructor(options = {}) {
    super();
    this.options = options;
    this.document = options.document || globalThis.document;
    if (!this.document) {
      throw new TypeError("FlexPay Checkout Modal nécessite un document DOM.");
    }
    this.messages = checkoutMessages(options.locale || "fr", options.messages || {});
    this.controller =
      options.controller ||
      new FlexPayCheckoutController({ ...options, document: this.document });
    this.confirmClose =
      options.confirmClose || ((message) => globalThis.confirm?.(message) !== false);
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
    this.trigger =
      typeof this.options.trigger === "string"
        ? this.document.querySelector(this.options.trigger)
        : this.options.trigger;
    if (!this.trigger) {
      throw new TypeError("FlexPay Checkout Modal : bouton déclencheur introuvable.");
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
      terminal: false,
    });

    try {
      const details = await this.controller.load();
      if (
        !details.terminal &&
        ["pending", "processing", "unknown"].includes(String(details.status))
      ) {
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
    if (
      !force &&
      this.pending &&
      !this.confirmClose(this.messages.closePendingConfirmation)
    ) {
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
    root.querySelector(".flexpay-checkout-title").textContent =
      this.messages.choosePaymentMethod;
    this.closeButton.textContent = "×";
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
      if (!['mobile_money', 'card'].includes(method)) {
        continue;
      }
      const button = this.button(
        method === "mobile_money" ? this.messages.mobileMoney : this.messages.card,
        "flexpay-checkout-method",
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
        const message =
          requestError?.code === "invalid_phone_number"
            ? this.messages.invalidPhoneNumber
            : requestError?.publicMessage || requestError?.message || this.messages.networkError;
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
    const message =
      (payload?.selected_method || payload?.paymentMethod) === "mobile_money"
        ? this.messages.confirmOnPhone
        : this.messages.waitingForConfirmation;
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
      "flexpay-checkout-button-primary",
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
        'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
      ),
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
    forward(CHECKOUT_EVENTS.initializing, () =>
      this.renderStatus(this.messages.initializing, true),
    );
    forward(CHECKOUT_EVENTS.pending, (detail) => this.renderPending(detail));
    forward(CHECKOUT_EVENTS.redirecting, () =>
      this.renderStatus(this.messages.redirectingToSecurePortal, true),
    );
    forward(CHECKOUT_EVENTS.verifying, () =>
      this.renderStatus(this.messages.verifyingPayment, true),
    );
    forward(CHECKOUT_EVENTS.success, () =>
      this.renderTerminal(this.messages.paymentSuccessful, "success"),
    );
    forward(CHECKOUT_EVENTS.failed, () =>
      this.renderTerminal(this.messages.paymentFailed, "error"),
    );
    forward(CHECKOUT_EVENTS.cancelled, () =>
      this.renderTerminal(this.messages.paymentCancelled, "error"),
    );
    forward(CHECKOUT_EVENTS.expired, () =>
      this.renderTerminal(this.messages.paymentExpired, "error"),
    );
    forward(CHECKOUT_EVENTS.unknown, () => this.renderUnknown());
    forward(CHECKOUT_EVENTS.error, (detail) =>
      this.renderError(detail.message || this.messages.networkError),
    );
  }

  emit(name, payload) {
    this.dispatchEvent(checkoutEvent(name, publicEventDetail(payload)));
  }
}
