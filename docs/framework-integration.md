# Intégration framework

Le cœur dépend uniquement de PSR-18, PSR-17, PSR-7 et PSR-3.

- [PHP natif](../examples/plain-php/README.md) : bootstrap Composer, fakes, stockage démonstration, routes et frontend.
- [CodeIgniter 4](../examples/codeigniter4/README.md) : `Config`, `Services`, store DB, contrôleur propriétaire, routes, migration, CSRF et modal.
- [Laravel](../examples/laravel/README.md) : config, service provider, Eloquent store, middleware propriétaire, Form Request, événements et tests HTTP.
- Symfony : enregistrer `FlexPayConfiguration`, un `Psr\Http\Client\ClientInterface`, deux factories PSR-17 et un logger ; binder ensuite `FlexPayClient` et `CheckoutManager`.

Dans tous les frameworks, l’application garde les responsabilités suivantes : authentification, autorisation par commande, calcul du montant, génération de référence, CSRF, throttling, transactions du store, queue/outbox, secrets et erreurs publiques. Le callback est exempté de CSRF uniquement sur sa route dédiée et reste authentifié par le mécanisme configuré.

Les exemples activent Mobile Money par défaut. Ajouter `PaymentMethod::CARD` seulement après injection d’une implémentation confirmée de `HostedCardCheckoutInterface`.
