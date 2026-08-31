export const FLEXPAY_CHECKOUT_MESSAGES = Object.freeze({
  fr: Object.freeze({
    choosePaymentMethod: "Choisissez votre moyen de paiement",
    mobileMoney: "Mobile Money",
    card: "Carte bancaire",
    phoneNumber: "Numéro Mobile Money",
    phonePlaceholder: "+243…",
    continue: "Continuer",
    back: "Retour",
    close: "Fermer",
    initializing: "Initialisation du paiement…",
    confirmOnPhone: "Confirmez la demande sur votre téléphone.",
    waitingForConfirmation: "Confirmation du paiement en attente.",
    redirectingToSecurePortal: "Redirection vers le portail de paiement sécurisé…",
    verifyingPayment: "Vérification du paiement…",
    paymentSuccessful: "Votre paiement est confirmé.",
    paymentFailed: "Le paiement n’a pas été confirmé.",
    paymentCancelled: "Le paiement a été annulé.",
    paymentExpired: "La session de paiement a expiré.",
    verificationTakingLonger:
      "La confirmation prend plus de temps que prévu. Votre paiement n’est pas déclaré en échec. Vous pouvez reprendre la vérification avec la même référence.",
    retryVerification: "Reprendre la vérification",
    invalidPhoneNumber: "Saisissez un numéro valide au format international.",
    networkError: "La connexion a été interrompue. Une nouvelle vérification sera tentée.",
    closePendingConfirmation:
      "Une confirmation est encore attendue. Fermer la fenêtre interrompra uniquement l’affichage de la vérification.",
  }),
  en: Object.freeze({
    choosePaymentMethod: "Choose your payment method",
    mobileMoney: "Mobile Money",
    card: "Payment card",
    phoneNumber: "Mobile Money number",
    phonePlaceholder: "+243…",
    continue: "Continue",
    back: "Back",
    close: "Close",
    initializing: "Initializing payment…",
    confirmOnPhone: "Confirm the payment request on your phone.",
    waitingForConfirmation: "Waiting for payment confirmation.",
    redirectingToSecurePortal: "Redirecting to the secure payment portal…",
    verifyingPayment: "Verifying payment…",
    paymentSuccessful: "Your payment is confirmed.",
    paymentFailed: "The payment could not be confirmed.",
    paymentCancelled: "The payment was cancelled.",
    paymentExpired: "The payment session has expired.",
    verificationTakingLonger:
      "Confirmation is taking longer than expected. Your payment has not been marked as failed. You can resume verification with the same reference.",
    retryVerification: "Resume verification",
    invalidPhoneNumber: "Enter a valid international phone number.",
    networkError: "The connection was interrupted. Verification will be retried.",
    closePendingConfirmation:
      "Confirmation is still pending. Closing this window only stops the on-screen verification.",
  }),
});

export function checkoutMessages(locale = "fr", overrides = {}) {
  const base = FLEXPAY_CHECKOUT_MESSAGES[locale] || FLEXPAY_CHECKOUT_MESSAGES.fr;
  return { ...base, ...overrides };
}
