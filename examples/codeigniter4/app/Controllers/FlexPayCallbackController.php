<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentApplicationService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;
use Holduix\FlexPay\Exception\FlexPayException;

final class FlexPayCallbackController extends ResourceController
{
    public function callback()
    {
        try {
            $headers = [];
            foreach ($this->request->headers() as $name => $header) {
                $headers[$name] = $header->getValueLine();
            }
            $event = Services::flexPay()->webhooks()->parse(
                $headers,
                (string) $this->request->getBody(),
                $this->request->getGet()
            );

            // Le service applicatif verrouille et confirme une seule fois.
            (new PaymentApplicationService())->handleFlexPayEvent($event);
            return $this->respond(['result' => 1]);
        } catch (FlexPayException $exception) {
            log_message('error', 'Callback FlexPay refusé : {message}', ['message' => $exception->getMessage()]);
            return $this->respond(['result' => 0], 400);
        }
    }
}
