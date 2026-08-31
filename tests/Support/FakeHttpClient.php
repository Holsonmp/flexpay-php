<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Support;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|Throwable|Closure(RequestInterface): ResponseInterface> */
    private array $queue;

    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param list<ResponseInterface|Throwable|Closure(RequestInterface): ResponseInterface> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new RuntimeException('Aucune réponse HTTP factice disponible.');
        }
        if ($next instanceof Throwable) {
            throw $next;
        }
        if ($next instanceof Closure) {
            return $next($request);
        }

        return $next;
    }
}
