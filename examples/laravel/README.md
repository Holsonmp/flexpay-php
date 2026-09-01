# Exemple Laravel

Intégrer ces fichiers dans une application Laravel existante et adapter le modèle `Order`, sa Policy `pay`, l’enregistrement du middleware `checkout.owner` et les rate limiters `flexpay-status`/`flexpay-callback`.

L’exemple contient :

- configuration par environnement et `FlexPayServiceProvider` ;
- migration, modèle et `EloquentCheckoutStore` ;
- contrôleur checkout, contrôleur callback et Form Request ;
- middleware de propriété qui charge la commande par référence et appelle la Policy ;
- routes web authentifiées avec CSRF et route API callback throttlée ;
- événement/listener applicatif, job de confirmation, vue et tests HTTP indicatifs.

Définir `FLEXPAY_URL_API`, `FLEXPAY_URL_API_CARD`, `FLEXPAY_MERCHANT`, `FLEXPAY_TOKEN` et `FLEXPAY_CALLBACK_TOKEN` dans le gestionnaire de secrets. L’accusé callback ne contient pas de `checkoutId`.

Mobile Money est activé par défaut. La carte exige une implémentation vérifiée de `HostedCardCheckoutInterface`, enregistrée dans le conteneur et passée au `FlexPayClient`, avant d’ajouter `PaymentMethod::CARD`.

Les callbacks ne sont jamais une preuve autonome : `CheckoutManager` relit le fournisseur. Les effets lourds doivent être envoyés vers une queue/outbox et supporter le replay.
