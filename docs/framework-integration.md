# Intégration framework

Le framework adapte quatre contrats : authentification/autorisation de la session, `CheckoutStoreInterface`, mapping HTTP, et effets métier après transition.

## Schéma recommandé

Une table de sessions contient au minimum : `checkout_id` unique et opaque, `merchant_reference` unique, montant, devise, méthodes, méthode choisie, statut normalisé, état checkout, identifiant fournisseur indexé, URL de checkout protégée, dates, version et payload de reconstruction. Une table de transitions possède une contrainte `(checkout_id, version)`. Une table de callbacks possède un `event_id` unique.

L’application doit :

- exécuter `save` et `recordTransition` dans une transaction réelle ;
- contrôler la version attendue dans l’`UPDATE` ;
- chiffrer ou protéger l’URL hébergée lorsqu’elle contient un token ;
- ne jamais utiliser le stockage mémoire/fichier en production ;
- lier la session à son propriétaire sans exposer cette relation dans le package ;
- rendre les effets métier idempotents avec une contrainte unique applicative.

Consultez `examples/codeigniter4`, `examples/laravel` et `examples/plain-php`.
