# Limitations

Les points suivants ne sont pas prouvés par un contrat fournisseur actuel et bloquent une déclaration de release stable prête à la production :

- endpoints et payloads Mobile Money/statut issus d’une intégration historique ;
- ensemble exact des statuts et codes fournisseur ;
- création d’une session carte publique/éphémère et comportement 3DS ;
- signature cryptographique ou allowlist réseau officielle des callbacks ;
- idempotence native du fournisseur ;
- remboursement et annulation fournisseur ;
- garanties de rapprochement lorsque référence, montant ou devise ne sont pas retournés ;
- SLA, rate limits, rétention et ordre des événements.

La carte est donc désactivée sans adaptateur injecté. Les statuts non reconnus restent `unknown`. Les réponses successful incomplètes sont refusées.

La production nécessite des identifiants valides, HTTPS, une politique de logs, une persistance transactionnelle, contraintes uniques, traitement idempotent, vérification serveur, queue/outbox, monitoring, alertes, tests sandbox et validation formelle des contrats fournisseur.

`InMemoryCheckoutStore`, `FileCheckoutStore` et les clients/adaptateurs fakes ne conviennent pas à la production. Les exemples framework doivent être adaptés aux modèles d’utilisateur/commande, au middleware et aux transactions de l’application.
