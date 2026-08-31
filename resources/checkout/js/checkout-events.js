export const CHECKOUT_EVENTS = Object.freeze({
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
  error: "flexpay:error",
});

export function publicEventDetail(payload = {}) {
  return {
    checkoutId: String(payload.checkout_id || payload.checkoutId || ""),
    merchantReference: String(
      payload.merchant_reference || payload.merchantReference || "",
    ),
    paymentMethod: payload.selected_method || payload.paymentMethod || null,
    status: String(payload.status || "unknown"),
    checkoutState: String(
      payload.checkout_state || payload.checkoutState || payload.status || "unknown",
    ),
    terminal: payload.terminal === true,
    message: String(payload.message || ""),
  };
}

export function checkoutEvent(name, detail) {
  if (typeof CustomEvent === "function") {
    return new CustomEvent(name, { detail });
  }

  const event = new Event(name);
  Object.defineProperty(event, "detail", { value: detail, enumerable: true });
  return event;
}
