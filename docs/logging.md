# Journalisation

Un logger PSR-3 est optionnel ; `NullLogger` est utilisé par défaut.

Le transport journalise méthode, endpoint API sans secret, statut HTTP, durée, tentative, référence marchande et correlation ID local. La redaction récursive masque authorization, tokens, secrets, identifiants marchand sensibles, PAN/CVV et téléphones.

Le contenu complet d’une requête n’est jamais loggé. Le checkout carte n’emprunte pas le transport et son URL sensible n’est donc jamais journalisée par le package.
