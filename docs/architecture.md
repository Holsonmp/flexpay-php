# Architecture

Le package sépare les contrats fournisseur, l’orchestration applicative et l’interface optionnelle.

1. `FlexPayClient` assemble configuration, transport PSR-18, factories PSR-17, logger PSR-3, validation, redaction et services.
2. `MobileMoneyService`, `TransactionService`, `CardPaymentService` et `WebhookService` exposent des DTO typés.
3. `ApiTransport` ajoute le Bearer uniquement aux appels serveur, borne les réponses, classifie les erreurs et ne retente que les lectures explicitement sûres.
4. `StatusMapper` normalise des valeurs exactes ; une valeur inconnue reste `unknown`.
5. `CheckoutManager` orchestre une `CheckoutSession`, une machine à états et un `CheckoutStoreInterface` fourni par l’application.
6. Le frontend appelle exclusivement les endpoints same-origin de l’application. Il n’accède ni au Bearer, ni au montant décisionnel, ni au fournisseur.

## Frontières

- Le package ne lit pas l’environnement, la session utilisateur ou la base de données.
- L’application crée les références et montants, contrôle la propriété du checkout, fournit CSRF/rate limiting et implémente un store transactionnel.
- `InMemoryCheckoutStore` est un fake de test. Les stores d’exemple illustrent les contraintes uniques et le verrou optimiste sans prétendre couvrir toutes les exigences opérationnelles.
- Le listener est appelé après persistance ; les effets lourds doivent être envoyés vers une queue/outbox applicative et rester idempotents.
- La carte est une capacité injectée. Aucun endpoint de création de session publique n’est inventé dans le cœur.

## Fiabilité

Une création Mobile Money n’est jamais retentée automatiquement. Un identifiant reçu par initiation, retour ou callback est d’abord persisté comme candidat non fiable et rejouable. Les lectures peuvent être retentées avec backoff borné ; seul un tuple fournisseur complet lie définitivement le candidat. Avant une lecture, une marque de rafraîchissement limite les appels concurrents, mais un callback throttlé n’est pas dédupliqué tant que sa vérification n’a pas abouti. `successful`, `failed`, `cancelled` et `expired` sont terminaux ; `successful` n’est jamais rétrogradé.

Les réponses et callbacks sont lus une seule fois, avec limites de taille, profondeur, nombre de champs et longueur scalaire. La priorité est la corrélation exacte, pas l’acceptation permissive de formes inconnues.
