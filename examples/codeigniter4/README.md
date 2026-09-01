# Exemple CodeIgniter 4

Copier uniquement les fichiers nécessaires dans une application CodeIgniter 4 existante et adapter les namespaces, la table `orders`, son champ `user_id`, les aliases de throttling et la politique d’autorisation.

L’exemple fournit :

- `FlexPayPackage` pour lire les secrets de l’environnement ;
- `Services` pour PSR-18, `FlexPayClient`, le store et `CheckoutManager` ;
- migration, repository à version optimiste et contraintes uniques ;
- contrôleur de création/détails/initialisation/statut/retour/callback ;
- routes avec throttling statut/callback ;
- vue et JavaScript modal avec CSRF.

Exclure uniquement la route callback du filtre CSRF. Toutes les routes navigateur restent sous l’authentification de l’application et le contrôleur compare la commande à `session('user_id')`. L’accusé callback ne rend aucun identifiant interne.

Définir au minimum `FLEXPAY_URL_API`, `FLEXPAY_URL_API_CARD`, `FLEXPAY_MERCHANT`, `FLEXPAY_TOKEN` et `FLEXPAY_CALLBACK_TOKEN`. Ne jamais versionner `.env`.

Mobile Money est la seule méthode activée dans le contrôleur. Pour la carte, implémenter et injecter `HostedCardCheckoutInterface` à partir d’un contrat fournisseur confirmé, puis ajouter `PaymentMethod::CARD`.

Ces fichiers sont un exemple d’intégration, pas une application complète ni un store universel de production. Les effets métier doivent passer par une queue/outbox idempotente.
