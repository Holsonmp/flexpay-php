# Mapping des statuts

Le mapper compare des valeurs exactes, insensibles à la casse et aux séparateurs espace/tiret. Il n’utilise aucune sous-chaîne : `unpaid`, `not_successful` et un message « Payment successful » restent `unknown`.

| Brut reconnu | Normalisé | Terminal | Origine |
|---|---|---:|---|
| `0`, `success`, `successful`, `paid`, `approved`, `completed`, `complete`, `verified`, `ok` | `successful` | oui | `success` et `0` observés dans fixtures historiques ; alias restants hérités, non validés fournisseur |
| `2`, `pending`, `awaiting`, `initiated`, `request` | `pending` | non | `pending` observé ; alias restants hérités, non validés |
| `processing`, `in_progress` | `processing` | non | défensif, non observé |
| `requires_action`, `action_required` | `requires_action` | non | état adaptateur/local, non confirmé fournisseur |
| `created` | `created` | non | état local, non observé fournisseur |
| `1`, `failed`, `failure`, `declined`, `error`, `rejected`, `invalid`, `insufficient` | `failed` | oui | `failed`/`1` historiques ; alias restants non validés |
| `cancelled`, `canceled` | `cancelled` | oui | fixture de robustesse, pas une observation fournisseur |
| `expired`, `timeout` | `expired` | oui | fixture de robustesse/local, pas une observation fournisseur |
| autre, booléen, float ou champ absent | `unknown` | non | politique fail-closed |

Les clés parcourues sont `transaction_status`, `payment_status`, `status`, `state`, `result`, `response_code`, `responseCode`, `code`, y compris dans des objets imbriqués. Une nouvelle valeur doit être ajoutée avec sa provenance, une fixture et des tests ; elle ne doit jamais être déduite à partir d’un message libre.

Le mapping lexical ne constitue pas, à lui seul, une confirmation. `TransactionService` rétrograde un `successful` en `unknown` lorsque l’identifiant demandé n’est pas renvoyé à l’identique ou que référence, montant et devise sont absents. Le consommateur compare ensuite ce tuple complet à sa commande serveur ; seul `CheckoutManager` réalise cette comparaison automatiquement.
