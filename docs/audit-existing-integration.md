# Audit de l’intégration source

L’audit détaillé est livré dans le projet hôte : `../../../docs/audit-existing-integration.md`.

Résumé : Mobile Money utilise un POST JSON C2B authentifié par Bearer ; la carte utilise dans le chemin actif une URL de checkout hébergé ; deux endpoints GET servent à la vérification ; le callback accepte GET, formulaire et JSON avec token applicatif optionnel. Aucun remboursement, annulation fournisseur, HMAC, opérateur imposé ou mécanisme d’idempotence FlexPay n’est prouvé.

Les fixtures du package distinguent explicitement les contrats provenant du fake legacy des scénarios de robustesse non observés.
