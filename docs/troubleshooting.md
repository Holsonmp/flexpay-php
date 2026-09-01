# Dépannage

| Symptôme | Vérification |
|---|---|
| Token invalide / HTTP 401 | Secret du bon environnement, sans espaces ; ne jamais l’afficher. |
| Mauvais endpoint | Comparer les URLs configurées au contrat marchand ; les défauts sont historiques. |
| Client PSR-18 absent | Installer/configurer une implémentation telle que Guzzle et des factories PSR-17. |
| Callback non reçu | HTTPS public, route/reverse proxy, taille, throttling et `X-FlexPay-Callback-Token`. |
| Callback refusé | Header unique, token constant-time, référence présente, body JSON/form valide et sous 64 Ko par défaut. |
| Transaction bloquée pending | Respecter le délai de refresh, vérifier côté serveur, monitorer et rapprocher manuellement. |
| Timeout de création | Considérer le résultat ambigu ; ne pas recréer automatiquement. |
| URL carte refusée | Adaptateur injecté, HTTPS, hôte exact, aucun userinfo/Bearer. |
| JSON invalide / HTML / XML | `InvalidResponseException`; vérifier endpoint/proxy, utiliser `correlationId`. |
| Polling trop fréquent | Ajuster `statusMinimumRefreshSeconds` et intervalles checkout, sans forçage client. |
| Asset introuvable | `npm run build`, puis `vendor/bin/flexpay-assets publish <cible>`. |
| Écrasement asset refusé | Vérifier la cible, puis utiliser explicitement `--force` sur des fichiers réguliers. |
| Carte désactivée | Faire valider le contrat hébergé et injecter `HostedCardCheckoutInterface`. |

Ne copiez pas `rawResponse` dans une réponse navigateur. Pour un diagnostic, conserver le type d’erreur, statut HTTP, hôte et `correlationId` dans les logs serveur filtrés.
