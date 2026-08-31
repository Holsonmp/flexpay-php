<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutOwner
{
    public function __construct(private readonly CheckoutStoreInterface $store)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->store->findById((string) $request->route('checkoutId'));
        abort_if($session === null, 404);
        // Charger la commande par merchantReference et appeler sa Policy Laravel.
        // abort_unless($request->user()->can('pay', $order), 403);
        return $next($request);
    }
}
