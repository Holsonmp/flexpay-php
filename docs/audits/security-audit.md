# Audit de sécurité

## Méthode

Un scan multi-passe scellé (`7a627d7b-254d-44cd-91c6-aad2cb237f6c`) a couvert 6 028 fichiers visibles, 20 revues indépendantes et produit 36 occurrences : 7 élevées, 19 moyennes, 9 faibles, 1 informationnelle, aucune critique. Plusieurs occurrences dupliquaient les mêmes causes racines ; le comptage brut n’est donc pas un nombre de vulnérabilités uniques.

La couverture annoncée par le scan est complète. Le répertoire a changé pendant le scan, puis un baseline Git propre a été confirmé avant les corrections. Un investigateur indépendant en lecture seule a regroupé les occurrences élevées en deux défauts racines.

## Critique

Aucun constat critique.

## Élevé

### Bearer serveur dans l’URL carte et le stockage — corrigé

Cause : `CardPaymentService` réutilisait `apiToken` comme paramètres navigables ; `CheckoutSession` persistait l’URL. Correction : suppression de cette construction, carte désactivée sans `HostedCardCheckoutInterface`, validation stricte du résultat adaptateur, rejet du token encodé et omission de l’URL du stockage. Tests : carte sans adaptateur, fake sûr, adaptateur malveillant, sérialisation sans URL.

### Identifiant fournisseur non fiable pouvant confirmer une autre transaction — corrigé

Cause : l’initiation/retour/callback pouvait lier l’identifiant avant lecture et les services inventaient ou acceptaient des valeurs absentes. Correction : candidat persistant mais explicitement non fiable et rejouable, identifiant retourné obligatoire et égal à l’identifiant interrogé, réussite complète seulement si référence/montant/devise présents et identiques, seconde lecture après un succès d’initiation. Un callback throttlé n’est marqué traité qu’après vérification. Tests : succès sparse à l’initiation/lecture/callback, mismatch d’identifiant, erreur réseau puis retry, ordres callback/retour et flux légitime complet.

Aucun problème élevé connu ne reste ouvert dans le code corrigé. La validation fournisseur manquante bloque néanmoins une release stable.

## Moyen

Corrigés : autorisation propriétaire exécutable dans les exemples, callbacks fermés par défaut, réponse callback sans checkout interne, auth avant parsing, budgets corps/structure, query token refusée, refresh borné, déduplication sémantique, mapping exact, workflows sans déploiement et actions épinglées.

Ouvert/documenté : le package ne peut garantir la livraison exactement-once d’un effet externe après persistance. Le store applicatif doit coupler transition et outbox transactionnelle. Il n’existe aucune signature cryptographique fournisseur prouvée ; un token applicatif, HTTPS et rate limiting sont la protection disponible jusqu’à validation du contrat.

## Faible

Corrigés : publication d’assets anti-symlink/atomique, redirection JS fail-closed, réponse fournisseur bornée, corps JSON invalide résumé par taille/hash, logs minimisés, redaction de secrets dans texte libre. Les fixtures contenant des valeurs telles que `fixture-redacted` sont explicitement factices et ne sont pas des secrets.

## Informationnel

Les workflows et documents dépendants de l’ancien dépôt parent ont été supprimés ou remplacés. Aucun remote n’est configuré et la recherche de l’historique visible n’a trouvé aucun secret réel.

## Menaces vérifiées

Recherche ciblée : Authorization/téléphones/logs, exceptions, réponses navigateur, open redirect, `javascript:`/`data:`, SSRF/configuration URL, header injection, traversal/symlinks, désérialisation, `eval`, commandes, `innerHTML`, XSS message fournisseur, callbacks, doubles traitements, retries et supply chain. Aucun paiement réel, secret de production ni endpoint inventé n’a été utilisé.

## Limites

La sécurité opérationnelle dépend du fournisseur, du store, de l’autorisation applicative, de la queue/outbox, du proxy et des secrets. Un canal privé de vulnérabilité doit être configuré avant publication publique. Les endpoints/statuts/callback/carte doivent être validés avec une source fournisseur actuelle.
