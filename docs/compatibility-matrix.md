# Matrice de compatibilité

| Élément | État | Preuve/limite |
|---|---|---|
| PHP 8.1–8.4 | Supporté | contraintes Composer ; test local 8.3 |
| PSR-18/17/7/3 | Supporté | interfaces du cœur |
| CodeIgniter 4.5.7 | Adaptateur fourni | projet source |
| Laravel | Exemple fourni | non exécuté dans ce dépôt |
| PHP natif | Exemple fourni | dépend d’un client PSR-18 |
| Mobile Money C2B | Confirmé par code/tests fake | endpoint et payload exacts |
| Checkout carte hébergé | Confirmé par code/tests | query legacy reproduite |
| Statut générique/carte | Confirmé par code/tests | deux endpoints GET |
| Callback JSON/form/query | Confirmé par code/tests | token optionnel |
| Signature webhook | Non pris en charge | aucune preuve |
| Remboursement/B2C | Non pris en charge | aucun appel métier |
| Annulation fournisseur | Non prise en charge | aucune preuve |
| Idempotency-Key fournisseur | Non prise en charge | aucune preuve |
| Sandbox réel | À vérifier | test désactivé, aucun secret fourni |
