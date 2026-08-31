# Installation

## Après publication du package

```bash
composer require holduix/flexpay-php:^0.1
composer require guzzlehttp/guzzle nyholm/psr7
```

## Repository local

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/flexpay-php",
      "options": {"symlink": true}
    }
  ],
  "require": {
    "holduix/flexpay-php": "^0.1"
  }
}
```

Exécuter `composer install`, puis injecter un client PSR-18 et deux factories PSR-17. PHP 8.1 est la version minimale issue du projet source.
