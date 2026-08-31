# Contrat HTTP recommandé

Le package retourne des DTO ou tableaux neutres. L’application adapte ces opérations à son routeur et applique authentification, autorisation, CSRF et rate limiting.

| Route | Usage | Code nominal |
|---|---|---|
| `POST /payments/flexpay/checkouts` | Créer une session côté serveur | 201 |
| `GET /payments/flexpay/checkouts/{checkoutId}` | Lire la vue publique | 200 |
| `POST /payments/flexpay/checkouts/{checkoutId}/initialize` | Initialiser une seule fois | 200 ou 202 |
| `GET /payments/flexpay/checkouts/{checkoutId}/status` | Lire/rafraîchir le statut | 200 |
| `GET /payments/flexpay/checkouts/{checkoutId}/return` | Corréler le retour et vérifier | 200 ou redirection locale |
| `POST /payments/flexpay/callback` | Recevoir le signal fournisseur | 200 après traitement sûr |

## Vue publique

```json
{
  "checkout_id": "chk_opaque",
  "merchant_reference": "ORDER-2026-0001",
  "amount": 50,
  "currency": "USD",
  "allowed_methods": ["mobile_money", "card"],
  "selected_method": null,
  "status": "created",
  "checkout_state": "created",
  "terminal": false,
  "message": "Choisissez un moyen de paiement.",
  "next_poll_after_ms": 0
}
```

La réponse n’expose jamais le payload FlexPay, le token, le téléphone, l’email, l’identifiant fournisseur ni l’URL de checkout en dehors de l’action de redirection immédiate.

## Initialisation Mobile Money

Requête :

```json
{"payment_method":"mobile_money","phone_number":"+243970000003"}
```

Réponse :

```json
{
  "checkout_id": "chk_opaque",
  "selected_method": "mobile_money",
  "status": "pending",
  "checkout_state": "pending",
  "terminal": false,
  "message": "Confirmez la demande de paiement sur votre téléphone.",
  "action": {"type":"poll","poll_url":"/payments/flexpay/checkouts/chk_opaque/status","after_ms":4000}
}
```

## Initialisation carte

```json
{
  "checkout_id": "chk_opaque",
  "selected_method": "card",
  "status": "requires_action",
  "checkout_state": "redirecting",
  "terminal": false,
  "action": {"type":"redirect","redirect_url":"https://cardpayment.flexpay.cd/..."}
}
```

L’URL ne doit être conservée ni journalisée par le frontend.

## Erreurs et codes

- 400 : requête syntaxiquement invalide ;
- 401/403 : authentification ou autorisation de l’application ;
- 404 : session absente/non accessible ;
- 409 : conflit de version, double initialisation ou transition incompatible ;
- 422 : téléphone, méthode ou champ métier invalide ;
- 429 : vérifications trop fréquentes ;
- 502 : réponse fournisseur illisible ;
- 503 : fournisseur indisponible ;
- 504 : timeout fournisseur.

Le frontend lit toujours `status`, `terminal`, `action`, `message` et `error.code`, jamais le seul code HTTP.
