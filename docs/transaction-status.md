# Statut des transactions

Le package distingue les états internes suivants : `created`, `pending`, `processing`, `requires_action`, `successful`, `failed`, `cancelled`, `expired`, `unknown`.

Ils ne sont pas présentés comme des statuts officiels FlexPay. La valeur brute est toujours conservée.

| Valeurs brutes confirmées ou présentes dans le legacy | Normalisé |
|---|---|
| `0`, `success`, `successful`, `paid`, `approved`, `completed`, `verified`, `ok` | `successful` |
| `2`, `pending`, `awaiting`, `initiated`, `request` | `pending` |
| `processing`, `in_progress` | `processing` |
| `1`, `failed`, `failure`, `declined`, `error`, `rejected` | `failed` |
| `cancelled`, `canceled` | `cancelled` |
| `expired`, `timeout` | `expired` |
| autre | `unknown` |

`created` et `requires_action` complètent le modèle interne pour éviter une perte de sens si ces valeurs sont reçues ; elles ne sont pas observées dans les fixtures legacy.

Une valeur inconnue ne déclenche aucune confirmation automatique.
