# Guide de migration

## Migration séparée du checkout

| `FLEXPAY_DRIVER` | `FLEXPAY_CHECKOUT_UI` | Usage transitoire |
|---|---|---|
| `legacy` | `legacy` | rollback complet |
| `package` | `legacy` | compatibilité de la livraison 0.1 |
| `legacy` | `package` | nouvelle UX sur un adaptateur HTTP legacy validé |
| `package` | `package` | nouveau parcours complet |

En production, `CheckoutFeaturePolicy` retourne `legacy` tant que `FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION=false`. La valeur ne doit être changée qu’après staging, validation fournisseur, monitoring et test des effets métier.

Ordre recommandé : ajouter le schéma/repository, créer les routes serveur, tester les callbacks et effets métier, publier les assets, activer l’UI en développement, tester staging, puis autoriser la production. Ne supprimez ni modal, contrôleurs ni driver legacy avant cette validation.

## Correspondance

| Legacy | Package |
|---|---|
| `initiateC2B()` | `mobileMoney()->createPayment()` |
| `initiateCardCheckout()` | `card()->createPayment()` |
| `checkTransaction()` | `transactions()->getStatus()` |
| `checkCardOrder()` | `transactions()->getCardOrderStatus()` |
| parsing contrôleur | `webhooks()->parse()` |
| `resolveTransactionStatus()` | `NormalizedPaymentStatus` + raw status |

## Déploiement progressif Étreinte

1. `composer install` installe le repository path.
2. Garder `FLEXPAY_DRIVER=legacy` partout.
3. Publier les assets dans `assets/vendor/flexpay-checkout`.
4. En développement, définir `FLEXPAY_CHECKOUT_UI=package` et `FLEXPAY_FAKE_MODE=1`.
5. Tester l'adaptateur UI sur les routes existantes `/preorder/create` et `/preorder/status`.
6. Définir ensuite `FLEXPAY_DRIVER=package` en conservant le fake.
7. Comparer payloads et parsing via les tests, jamais avec deux créations réelles.
8. Vérifier le sandbox une seule fois avec autorisation explicite.
9. Déployer le code avec le driver et l'UI de production encore `legacy`.
10. Après validation formelle, définir `FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION=true`, puis activer séparément l'UI et le driver.

L'adaptateur `PackageFlexPayClientAdapter` conserve les tableaux attendus par `PaymentService`; les contrôleurs, routes et règles métier restent inchangés.

L'interface Étreinte utilise `assets/themes/magazine/js/flexpay-checkout-etreinte.js` comme couche de transition. Elle conserve le formulaire, le calcul du montant et les endpoints actuels, mais délègue le choix du moyen de paiement, l'accessibilité du modal, le polling et la validation de redirection au composant du package. La création de commande n'est envoyée qu'après le choix du moyen de paiement. Cet adaptateur ne remplace pas le contrat HTTP générique documenté pour les nouvelles applications.

Aucune migration SQL checkout supplémentaire n'est requise pour Étreinte : sa persistance et son idempotence applicatives existantes sont conservées. Les exemples CodeIgniter et Laravel fournissent, eux, un repository et une migration pour une application qui adopte directement le contrat générique.

## Rollback

Remettre `FLEXPAY_CHECKOUT_UI=legacy` et `FLEXPAY_DRIVER=legacy`, vider le cache de configuration si nécessaire et redéployer. Aucune migration de base n'est nécessaire et le code legacy n'a pas été supprimé. Les références/paiements existants restent lisibles. Les assets publiés peuvent rester en place : ils ne sont plus chargés lorsque l'UI legacy est active.

Ne lancez jamais les deux drivers pour une même transaction.
