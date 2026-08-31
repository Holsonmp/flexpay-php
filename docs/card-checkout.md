# Checkout carte hébergé

La librairie ne collecte jamais PAN, CVV ou date d’expiration. `CardPaymentService` produit uniquement l’URL du portail FlexPay confirmée par le legacy.

`CheckoutRedirectValidator` exige une URL valide, HTTPS hors sandbox/local explicite, sans identifiants URL et avec un hôte présent dans l’allowlist. La session passe par `requires_action`, est sauvegardée, puis passe à `redirecting` avant que l’action publique soit retournée.

Le retour navigateur n’est qu’un déclencheur de vérification. Les paramètres de statut sont ignorés ; une référence fournisseur éventuelle sert seulement à corréler la lecture serveur. Le callback suit la même règle.
