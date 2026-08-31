# Sécurité

## Checkout

- Le navigateur reçoit un `checkoutId` opaque et n’envoie jamais montant, devise ou URLs de confiance.
- L’accès à une session doit être autorisé par l’application hôte afin d’éviter l’énumération.
- L’initialisation exige un CSRF en contexte session et le statut doit être rate-limité.
- Le frontend reste same-origin et ne contient aucun credential FlexPay.
- `CheckoutRedirectValidator` impose HTTPS, une allowlist d’hôtes et refuse credentials et schémas actifs.
- Les callbacks sans signature confirmée déclenchent une vérification serveur.
- L’URL de checkout peut contenir un token legacy : protégez-la au repos, ne la journalisez pas et ne la rendez que dans l’action de redirection.
- Les événements PHP/JavaScript n’exposent ni téléphone complet, ni réponse brute, ni credential.

- Injecter les secrets depuis un secret manager ou l’environnement applicatif.
- Exiger HTTPS en production.
- Ne jamais logger l’URL checkout complète, le Bearer, un token, un PAN, un CVV ou un téléphone complet.
- Ne jamais collecter de données carte : rediriger vers le checkout hébergé.
- Conserver une référence marchande unique dans l’application.
- Ne jamais retenter automatiquement une création ambiguë.
- Relire le statut serveur avant confirmation lorsque possible.
- Comparer montant, devise et référence à la transaction locale.
- Verrouiller la transaction et rendre les effets métier idempotents.
- Répondre vite au callback et déporter les travaux lourds.

Limites : le callback legacy n’a pas de signature FlexPay confirmée ; le token applicatif est optionnel et peut être transmis dans l’URL. Un token callback fort et le header sont préférables, mais ne remplacent pas la vérification serveur.

Le package filtre les réponses brutes. `checkoutUrl` reste nécessaire à la redirection et contient des secrets selon le contrat legacy : il ne doit pas être sérialisé dans des logs ou historiques.
