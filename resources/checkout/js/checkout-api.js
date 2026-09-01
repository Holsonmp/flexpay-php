/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

export class FlexPayCheckoutHttpError extends Error {
  constructor(message, status = 0, payload = {}) {
    super(message);
    this.name = "FlexPayCheckoutHttpError";
    this.status = status;
    this.code = payload?.error?.code || "checkout_http_error";
    this.publicMessage = payload?.error?.message || payload?.message || message;
    this.payload = payload;
  }
}

export class FlexPayCheckoutApi {
  constructor(options) {
    if (!options?.checkoutId) {
      throw new TypeError("FlexPay Checkout : checkoutId est obligatoire.");
    }
    if (typeof (options.fetch || globalThis.fetch) !== "function") {
      throw new TypeError("FlexPay Checkout : une implémentation de fetch est obligatoire.");
    }

    this.checkoutId = String(options.checkoutId);
    this.endpoints = {
      details: options.endpoints?.details || "/payments/flexpay/checkouts/{checkoutId}",
      initialize:
        options.endpoints?.initialize ||
        "/payments/flexpay/checkouts/{checkoutId}/initialize",
      status:
        options.endpoints?.status ||
        "/payments/flexpay/checkouts/{checkoutId}/status",
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
        body: payload === null ? undefined : JSON.stringify(payload),
        signal,
      });
    } catch (error) {
      if (error?.name === "AbortError") {
        throw error;
      }
      throw new FlexPayCheckoutHttpError(
        "Le serveur de paiement est momentanément inaccessible.",
        0,
      );
    }

    let data = {};
    try {
      data = await response.json();
    } catch (_error) {
      throw new FlexPayCheckoutHttpError(
        "La réponse du serveur de paiement est illisible.",
        response.status,
      );
    }

    if (!response.ok) {
      throw new FlexPayCheckoutHttpError(
        data?.message || "La requête de paiement a échoué.",
        response.status,
        data,
      );
    }

    return data;
  }

  resolveEndpoint(template) {
    return String(template)
      .split("{checkoutId}")
      .join(encodeURIComponent(this.checkoutId));
  }
}
