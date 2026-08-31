# Tests

La suite comprend les contrats bas niveau historiques, la machine à états, les doubles initialisations, callback/retour dans les deux ordres, le filtrage public, l’allowlist de redirection, la publication des assets et les parcours JavaScript headless/modal.

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
vendor/bin/phpunit
vendor/bin/phpstan analyse src tests
vendor/bin/phpcs --standard=PSR12 src tests
npm ci
npm test
npm run build
```

Les fixtures `legacy_fake_mode` et `legacy_integration_test` proviennent des contrats du dépôt. Celles marquées `robustness_only_not_observed` vérifient la robustesse et ne prétendent pas représenter une réponse FlexPay observée.

Le sandbox est ignoré sauf si toutes les variables suivantes existent et si `FLEXPAY_SANDBOX_ENABLED=true` : `FLEXPAY_BASE_URL`, `FLEXPAY_MERCHANT_ID`, `FLEXPAY_API_TOKEN`, `FLEXPAY_CALLBACK_URL`, `FLEXPAY_TEST_PHONE`, `FLEXPAY_TEST_AMOUNT`, `FLEXPAY_TEST_CURRENCY`.

Ce test est potentiellement transactionnel, exclu de la CI et ne doit utiliser que des identifiants sandbox avec un montant minimal autorisé. Aucun secret n’est imprimé.

La CI collecte la couverture avec Xdebug. `scripts/check-coverage.php` échoue sous 85 %. Les zones critiques — transitions, filtrage, URL, callback, double initialisation, mapping et redaction — sont testées directement. La couverture locale nécessite Xdebug ou PCOV ; sans pilote, aucun pourcentage ne doit être revendiqué. Les assets distribués sont reconstruits et comparés au dépôt.
