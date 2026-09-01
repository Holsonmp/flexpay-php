# Tests

## Commandes

```bash
composer test
composer analyse
composer style
composer examples:test
npm test
npm run build
composer coverage
composer installation:test
composer release:check
```

- `tests/Unit` couvre configuration, validation, redaction, mapping, machine à états et politiques.
- `tests/Contract` valide payloads, transport, statuts, carte injectée et callbacks.
- `tests/Integration` exécute checkout, ordre callback/retour, déduplication, concurrence et assets.
- `tests/JavaScript` teste API, CSRF, polling, erreurs, redirections, événements, modal, focus et traductions dans JSDOM.
- `fixtures` contient des réponses historiques ou de robustesse explicitement marquées ; aucune n’est une preuve fournisseur actuelle.

La couverture CI utilise Xdebug et échoue sous 85 % global via `scripts/check-coverage.php`. Les zones critiques sont exercées par matrices et scénarios malveillants. Un poste sans Xdebug/PCOV ne peut pas produire de mesure locale : l’absence d’extension doit être rapportée, pas masquée.

`SandboxTest` ne s’exécute que si `FLEXPAY_SANDBOX_ENABLED=true` et si les identifiants de sandbox sont volontairement fournis. La CI normale n’effectue jamais de paiement réel.

`tools/check-examples.php` lint tous les fichiers PHP, valide leurs imports package et exécute le fake natif. `tools/check-installation.php` consomme le package via repository path dans un dossier temporaire sans réseau fournisseur. `tools/check-license-headers.php` et `tools/check-api-docs.php` empêchent les divergences d’attribution/API.
