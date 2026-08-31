<?php

declare(strict_types=1);

namespace App\Services;

use Holduix\FlexPay\DTO\Response\WebhookEvent;

final class PaymentApplicationService
{
    public function handleFlexPayEvent(WebhookEvent $event): void
    {
        // 1. Ouvrir une transaction SQL et verrouiller le paiement par référence.
        // 2. Ignorer un événement déjà appliqué.
        // 3. Relire le statut FlexPay et comparer montant, devise et référence.
        // 4. Appliquer la transition puis émettre les effets métier une seule fois.
    }
}
