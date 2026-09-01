# Compatibilité ascendante

Baseline comparée : API documentée 0.2 et symboles du commit initial visible.

## Conservé

`FlexPayClient`, configurations, services, DTO, enums, exceptions, checkout, stores/contrats, sérialiseurs, CLI et exports JavaScript gardent leur namespace et leurs méthodes. Les nouveaux paramètres de `FlexPayConfiguration` et `FlexPayClient` sont optionnels et ajoutés en fin de constructeur pour préserver les appels positionnels existants.

`checkoutUrl()` existe encore sur `CheckoutSession`, mais retourne `null` après hydratation/persistance ; `checkout_url` historique est ignoré. L’action immédiate continue de rendre l’URL sûre. Ce changement de comportement est volontairement sécuritaire. Une procédure obligatoire d’inventaire, réécriture, rotation de token et purge des sauvegardes est décrite dans `docs/security.md` ; le package ne modifie jamais une base applicative de lui-même.

`provider_transaction_candidate` est ajouté au payload de session. Ce champ est facultatif à l’hydratation : les enregistrements 0.2 restent lisibles. Il stocke un identifiant explicitement non vérifié pour permettre la reprise après erreur réseau ou crash ; il n’alimente pas la recherche par identifiant fournisseur et disparaît après liaison confirmée.

`CheckoutFeaturePolicy` est conservée bien qu’elle n’ait plus de rôle dans le dépôt autonome. Elle doit être dépréciée formellement avant une suppression majeure.

## Rupture sécuritaire assumée

`CardPaymentService::createPayment()` lève désormais `UnsupportedOperationException` sans adaptateur. L’ancien comportement exposait un credential serveur et ne pouvait être maintenu. Migration : implémenter `HostedCardCheckoutInterface` uniquement à partir d’un contrat fournisseur confirmé, l’injecter en dernier argument de `FlexPayClient`, puis tester sandbox.

Les callbacks avec `callbackToken=''` sont désormais refusés sauf opt-out explicite `allowUnauthenticatedCallbacks=true`. Le token query est refusé sauf opt-in. Migration : configurer un secret callback dans un header unique et mettre à jour le fournisseur/proxy.

Les statuts ambigus précédemment reconnus par sous-chaîne/booléen deviennent `unknown`. Les réponses successful sans corrélation complète sont refusées. Ces ruptures ferment des chemins fail-open.

## API retirée

Aucune classe PHP publique n’est supprimée. Les seuls fichiers PHP retirés étaient des exemples CodeIgniter non exécutables et non namespacés dans le package. Les fichiers de documentation et workflows parent ne constituent pas une API runtime.

Recommandation SemVer : pré-release mineure `0.3.0-beta.1`, avec guide de migration sécurité dans changelog/docs, pas `1.0.0`.
