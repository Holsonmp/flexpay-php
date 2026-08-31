# Dépannage

| Symptôme | Vérification |
|---|---|
| Configuration manquante | merchant/token injectés, sans espace parasite |
| Callback refusé | même token dans configuration et header/query |
| Callback introuvable | présence de référence locale ou `orderNumber` |
| Checkout sans redirection | endpoint carte, URL sensible non tronquée avant redirection |
| JSON invalide/HTML | proxy ou endpoint incorrect ; consulter statut HTTP/correlation ID |
| Timeout de création | ne pas recréer ; relire avec la référence existante |
| Statut `unknown` | conserver raw status, ne pas confirmer, ajouter une fixture après preuve |
| Package non activé | `FLEXPAY_DRIVER=package` uniquement hors production dans cette phase |
| Classe package absente | exécuter `composer install` à la racine |

Les erreurs réseau ne doivent jamais être résolues en imprimant les payloads ou secrets.
