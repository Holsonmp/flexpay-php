# Démarrage rapide avec les fakes

1. Installer : `composer require holduix/flexpay-php:^0.2`.
2. Configurer `FlexPayConfiguration` avec des valeurs sandbox/fake, jamais des secrets dans le navigateur.
3. Construire un `FlexPayClient` PSR-18 et un `CheckoutManager` avec votre `CheckoutStoreInterface`.
4. Créer la session côté serveur depuis votre commande et votre montant.
5. Exposer les cinq routes du [contrat HTTP](http-contract.md).
6. Publier les assets : `vendor/bin/flexpay-assets publish public/vendor/flexpay`.
7. Monter le modal décrit dans [frontend-checkout.md](frontend-checkout.md).
8. Recevoir le callback comme signal et laisser `CheckoutManager::handleCallback()` vérifier le statut serveur.
9. Réagir à `PaymentSucceeded` dans un listener applicatif idempotent.

```php
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore;
use Holduix\FlexPay\Enum\PaymentMethod;

$checkout = new CheckoutManager($client, new InMemoryCheckoutStore());
$session = $checkout->create(new CreateCheckoutRequest(
    merchantReference: 'ORDER-2026-0001',
    amount: 50,
    currency: 'USD',
    allowedMethods: [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
    successUrl: 'https://example.test/payment/success',
    cancelUrl: 'https://example.test/payment/cancel',
    callbackUrl: 'https://example.test/payment/callback'
));
```

`InMemoryCheckoutStore` sert uniquement aux tests et démonstrations dans un même processus. Les exemples framework montrent une contrainte unique, un verrou/contrôle de version et une transaction.

Le fake ne doit appeler aucun endpoint FlexPay. Utilisez le client factice de `examples/plain-php` ou les fakes des tests ; n’activez jamais le sandbox sans autorisation et identifiants dédiés.
