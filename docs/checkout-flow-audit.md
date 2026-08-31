# Audit du parcours checkout existant

Date : 31 août 2026
Périmètre : projet Étreinte et package `holduix/flexpay-php`

## Niveaux de preuve

- **Code** : comportement directement lu dans les routes, contrôleurs, services, vues ou scripts.
- **Test** : comportement exercé par `tests/integration/preorder-payment-flow.sh` ou les suites du package.
- **Fixture** : forme provenant du mode fake legacy ou d’un test d’intégration ; ce n’est pas une réponse fournisseur réelle.
- **Décision de librairie** : comportement ajouté, configurable et non présenté comme un contrat FlexPay.
- **Non vérifié** : aucun élément fiable dans le dépôt.

## Point d’entrée et modal legacy

| Élément | Comportement | Preuve |
|---|---|---|
| Boutons | Les CTA `[data-preorder-modal-trigger]` ouvrent `#preorderModal`. | Code : `app/Views/themes/magazine/index.php` |
| Modal | Modal Bootstrap contenant commande, identité, livraison, Mobile Money/carte et total. | Code |
| Téléphone | Un numéro local de 9 chiffres est préfixé par `243`; le backend exige ensuite `243` et 9 chiffres. | Code : vue + `PreorderService` |
| Double clic | Le bouton submit est désactivé pendant l’AJAX. | Code : `assets/themes/magazine/js/main.js` |
| Création | `POST /preorder/create` crée la commande, réserve le stock puis initialise immédiatement FlexPay. | Code + test |
| Carte | Une `payment_url` déclenche une redirection après 900 ms. | Code + test |
| Mobile Money | Sans URL, le modal reste ouvert et le navigateur commence la vérification. | Code + test |

Le legacy ne sépare donc pas la session de checkout, la commande et l’initialisation fournisseur. La nouvelle couche ne reprend pas cette dépendance métier : l’application hôte crée d’abord une session opaque à partir de son propre montant et de sa propre commande.

## Polling legacy

- Appel immédiat puis `setInterval` toutes les **4 secondes**.
- Maximum **45 tentatives**, soit environ **180 secondes**.
- Une seule variable d’intervalle globale, remise à zéro lorsqu’un nouveau paiement est suivi.
- Arrêt sur `is_final` ou après la 45e tentative.
- Pas de pause lorsque l’onglet est masqué.
- Pas de backoff, pas de `nextPollAfter`, pas de coordination inter-onglets.
- Une erreur réseau consomme une tentative ; le paiement n’est pas explicitement transformé en échec.
- La fermeture du modal n’arrête pas explicitement le polling legacy.

Preuve : code. Les scénarios `pending`, `success` et `failed` sont confirmés par les tests ; le timeout visuel n’avait pas de message spécifique.

## Mobile Money legacy

1. La commande et un paiement local `initiated` sont persistés.
2. Le backend envoie le payload C2B confirmé dans l’audit principal.
3. Le mode fake retourne `pending` avec `orderNumber`.
4. `/preorder/status` relit le fournisseur lorsqu’une référence fournisseur existe.
5. Le callback ou le polling peut confirmer les effets métier sous verrou SQL.

Confirmé par fixture : `status`, `reference`, `orderNumber`, `amount`, `currency`, `callbackUrl`. Aucun opérateur n’est déduit ni envoyé. Les erreurs opérateur réelles et l’expiration réelle restent non vérifiées.

## Carte legacy

1. Le backend construit une URL hébergée sur l’hôte observé `cardpayment.flexpay.cd`.
2. La commande et le paiement local existent avant la redirection.
3. Le navigateur quitte l’application.
4. Les URLs de retour ajoutent `payment=approve|cancel|decline|home` et la référence locale.
5. Le retour affiche un état d’attente et relance `/preorder/status` ; il ne confirme pas seul le paiement.
6. Le callback peut arriver avant ou après le retour.

Le fallback actif ne reçoit pas d’identifiant fournisseur lors de la construction de l’URL. L’ordre carte, 3-D Secure, l’expiration du portail et les données réelles de retour restent non vérifiés.

## Persistance et statuts

- `payments.merchant_reference` est unique ; `provider_reference` est indexée.
- Statuts de paiement locaux : `initiated`, `callback_received`, `verified`, `failed`.
- Statuts de commande : `pending`, `paid`, `cancelled`, `fulfilled`.
- Statuts bruts confirmés par les fakes/tests : `success`/`0`, `failed`/`1`, `pending`/`2`.
- Le package conserve séparément statut brut, statut normalisé et état visuel de checkout.

## Callback et concurrence

- GET, formulaire POST et JSON sont acceptés.
- Le token applicatif optionnel peut être lu dans query/form ou `X-FlexPay-Callback-Token`.
- Aucune signature cryptographique FlexPay fiable n’est confirmée.
- Le callback est donc un signal ; seule une vérification serveur peut confirmer la nouvelle couche.
- Le legacy verrouille la ligne `payments` avec `FOR UPDATE` et protège un paiement déjà `verified`.
- Le package ajoute une empreinte de callback, un verrou optimiste de version et une machine à états. Les transactions SQL et contraintes d’unicité restent à la charge du repository hôte.

## Décisions nouvelles de la librairie

- Identifiant opaque `chk_` suivi de 32 caractères hexadécimaux.
- Délai initial de polling : 0 ms, intervalle 4 000 ms, maximum 10 000 ms, timeout 180 secondes.
- Trois erreurs réseau tolérées avant l’état visuel non terminal `unknown`.
- Cache serveur du statut fournisseur pendant 4 secondes.
- Pause du polling lorsque le document est masqué.
- Autorisation exacte des hôtes de checkout ; `cardpayment.flexpay.cd` est la valeur par défaut car elle est observée dans le projet.
- URL HTTPS obligatoire, sauf sandbox/local explicitement configuré.

Toutes ces valeurs sont configurables. Elles ne sont pas présentées comme des exigences du fournisseur.

## Non vérifié

- Signature ou identifiant d’événement callback fourni par FlexPay.
- Garantie d’idempotence côté fournisseur.
- Réponses sandbox/production et ordre garanti callback/retour.
- Limites de taux et entête `Retry-After`.
- Expiration et statuts opérateur réels.
- Remboursement, annulation fournisseur, paiement récurrent et B2C.
- PAN, CVV et date d’expiration : hors périmètre et jamais collectés.
