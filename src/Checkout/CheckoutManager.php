<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

use Closure;
use DateTimeImmutable;
use Holduix\FlexPay\Checkout\Contracts\CheckoutEventListenerInterface;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Checkout\DTO\CheckoutOperationResult;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Event\CheckoutEvent;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;
use Holduix\FlexPay\Checkout\Exception\CheckoutException;
use Holduix\FlexPay\Checkout\Exception\CheckoutNotFoundException;
use Holduix\FlexPay\Checkout\Mapper\CheckoutStatusMapper;
use Holduix\FlexPay\Checkout\Policy\CheckoutStateMachine;
use Holduix\FlexPay\Checkout\Policy\StatusRefreshPolicy;
use Holduix\FlexPay\Checkout\Security\CheckoutRedirectValidator;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\DTO\Response\PaymentResult;
use Holduix\FlexPay\DTO\Response\TransactionStatusResult;
use Holduix\FlexPay\DTO\Response\WebhookEvent;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Exception\FlexPayException;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\FlexPayClient;
use Holduix\FlexPay\Support\DataExtractor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CheckoutManager
{
    private readonly LoggerInterface $logger;
    private readonly CheckoutConfiguration $configuration;
    private readonly CheckoutStateMachine $stateMachine;
    private readonly CheckoutStatusMapper $statusMapper;
    private readonly StatusRefreshPolicy $refreshPolicy;
    private readonly CheckoutRedirectValidator $redirectValidator;
    private readonly DataExtractor $extractor;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /**
     * @param null|Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private readonly FlexPayClient $flexPayClient,
        private readonly CheckoutStoreInterface $store,
        ?LoggerInterface $logger = null,
        ?CheckoutConfiguration $configuration = null,
        private readonly ?CheckoutEventListenerInterface $eventListener = null,
        ?Closure $clock = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->configuration = $configuration ?? new CheckoutConfiguration();
        $this->stateMachine = new CheckoutStateMachine();
        $this->statusMapper = new CheckoutStatusMapper();
        $this->refreshPolicy = new StatusRefreshPolicy($this->configuration);
        $this->redirectValidator = new CheckoutRedirectValidator($this->configuration);
        $this->extractor = new DataExtractor();
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public function create(CreateCheckoutRequest $request): CheckoutSession
    {
        $this->validateCreateRequest($request);
        if ($this->store->findByMerchantReference(trim($request->merchantReference)) !== null) {
            throw new CheckoutConflictException(
                'Une session existe déjà pour cette référence marchande.',
                'duplicate_merchant_reference'
            );
        }

        $now = $this->now();
        $session = CheckoutSession::create(
            $this->newCheckoutId(),
            trim($request->merchantReference),
            $request->amount,
            strtoupper(trim($request->currency)),
            $this->uniqueMethods($request->allowedMethods),
            trim($request->successUrl),
            trim($request->cancelUrl),
            trim($request->callbackUrl),
            trim($request->customerName),
            trim($request->customerEmail),
            trim($request->description) ?: 'Paiement ' . trim($request->merchantReference),
            $now,
            $request->expiresAt
        );
        $this->store->save($session);
        $this->emit('CheckoutCreated', $session, null);

        return $session;
    }

    public function details(string $checkoutId): CheckoutPublicView
    {
        $session = $this->load($checkoutId);

        return $this->publicView($session);
    }

    public function initialize(InitializeCheckoutRequest $request): CheckoutOperationResult
    {
        $session = $this->load($request->checkoutId);
        $retryableInputState = in_array(
            $session->state(),
            [CheckoutState::CHOOSING_METHOD, CheckoutState::COLLECTING_DETAILS],
            true
        );
        if (
            ($session->selectedMethod() !== null && $session->selectedMethod() !== $request->paymentMethod)
            || ($session->state() !== CheckoutState::CREATED && !$retryableInputState)
        ) {
            throw new CheckoutConflictException(
                'La session a déjà été initialisée.',
                'checkout_already_initialized'
            );
        }
        if (!in_array($request->paymentMethod, $session->allowedMethods(), true)) {
            throw new CheckoutException(
                'Le moyen de paiement demandé n’est pas autorisé.',
                'payment_method_not_allowed',
                'Moyen de paiement : cette méthode n’est pas autorisée pour cette transaction.'
            );
        }

        $session->selectMethod($request->paymentMethod, $this->now());
        if ($session->state() === CheckoutState::CREATED) {
            $this->transition($session, CheckoutState::CHOOSING_METHOD, 'user');
        }
        if (
            $request->paymentMethod === PaymentMethod::MOBILE_MONEY
            && $session->state() === CheckoutState::CHOOSING_METHOD
        ) {
            $this->transition($session, CheckoutState::COLLECTING_DETAILS, 'user');
        }
        $this->transition($session, CheckoutState::INITIALIZING, 'application');

        try {
            if ($request->paymentMethod === PaymentMethod::MOBILE_MONEY) {
                return $this->initializeMobileMoney($session, $request->phoneNumber);
            }

            return $this->initializeCard($session, $request->paymentMethod);
        } catch (ValidationException $exception) {
            $this->logger->notice('Validation de checkout refusée.', [
                'checkout_id' => $session->id(),
                'error' => $exception->getMessage(),
            ]);
            $target = $request->paymentMethod === PaymentMethod::MOBILE_MONEY
                ? CheckoutState::COLLECTING_DETAILS
                : CheckoutState::CHOOSING_METHOD;
            $this->transition($session, $target, 'application');
            throw $exception;
        } catch (FlexPayException $exception) {
            $this->markInitializationUnknown($session, $exception);
            throw $exception;
        }
    }

    public function refreshStatus(RefreshCheckoutStatusRequest $request): CheckoutOperationResult
    {
        $session = $this->load($request->checkoutId);
        if ($session->normalizedStatus()->isFinal()) {
            return new CheckoutOperationResult($this->publicView($session), CheckoutAction::none(), true);
        }
        if (!$this->refreshPolicy->shouldRefresh($session, $this->now(), $request->forceProviderRefresh)) {
            return new CheckoutOperationResult(
                $this->publicView($session),
                $this->pollAction($session),
                true
            );
        }

        return $this->refreshFromProvider($session);
    }

    public function handleReturn(HandleCheckoutReturnRequest $request): CheckoutOperationResult
    {
        $session = $this->load($request->checkoutId);
        if ($session->normalizedStatus()->isFinal()) {
            return new CheckoutOperationResult($this->publicView($session), CheckoutAction::none(), true);
        }

        $providerReference = $this->extractor->firstScalar($request->queryParameters, [
            'orderNumber',
            'order_number',
            'ordernumber',
            'provider_reference',
            'providerReference',
            'transaction_id',
        ]);
        if ($session->providerTransactionId() === null && $providerReference !== null) {
            $expectedVersion = $session->version();
            $session->attachProviderTransactionId($providerReference, $this->now());
            $this->store->save($session, $expectedVersion);
        }

        if ($session->providerTransactionId() === null) {
            return new CheckoutOperationResult($this->publicView($session), $this->pollAction($session));
        }

        return $this->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
    }

    public function handleCallback(HandleCheckoutCallbackRequest $request): CheckoutOperationResult
    {
        $eventId = $this->callbackEventId($request);
        $event = $this->flexPayClient->webhooks()->parse(
            $request->headers,
            $request->rawBody,
            $request->queryParameters
        );
        $session = $this->findForWebhook($event);
        if ($this->store->hasProcessedCallback($eventId)) {
            return new CheckoutOperationResult($this->publicView($session), CheckoutAction::none(), true);
        }

        $this->assertWebhookMatchesSession($event, $session);
        if ($this->isOlderProviderEvent($event, $session)) {
            $this->store->markCallbackProcessed($eventId, $session->id());
            return new CheckoutOperationResult($this->publicView($session), CheckoutAction::none(), true);
        }

        if ($session->providerTransactionId() === null && $event->providerTransactionId !== null) {
            $expectedVersion = $session->version();
            $session->attachProviderTransactionId($event->providerTransactionId, $this->now());
            $this->store->save($session, $expectedVersion);
        }

        $result = $session->providerTransactionId() === null
            ? new CheckoutOperationResult($this->publicView($session), $this->pollAction($session))
            : $this->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));

        $latest = $this->load($session->id());
        $expectedVersion = $latest->version();
        $latest->markCallbackProcessed($this->now());
        $this->store->save($latest, $expectedVersion);
        $this->store->markCallbackProcessed($eventId, $latest->id());

        return new CheckoutOperationResult(
            $this->publicView($latest),
            $result->action,
            $result->idempotent
        );
    }

    private function initializeMobileMoney(
        CheckoutSession $session,
        string $phoneNumber
    ): CheckoutOperationResult {
        $result = $this->flexPayClient->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
            amount: $session->amount(),
            currency: $session->currency(),
            phoneNumber: $phoneNumber,
            merchantReference: $session->merchantReference(),
            callbackUrl: $session->callbackUrl(),
            description: $session->description()
        ));
        $targetState = $this->statusMapper->toCheckoutState($result->normalizedStatus);
        if (!$result->accepted && $targetState === CheckoutState::PENDING) {
            $targetState = CheckoutState::UNKNOWN;
        }
        $session->setProviderObservation(
            $result->normalizedStatus,
            $result->rawStatus,
            $result->providerTransactionId,
            null,
            $this->now(),
            $this->parseOccurredAt($result->occurredAt)
        );
        $this->transition($session, $targetState, 'provider');

        return new CheckoutOperationResult(
            $this->publicView($session),
            $targetState->isTerminal() ? CheckoutAction::none() : $this->pollAction($session)
        );
    }

    private function initializeCard(
        CheckoutSession $session,
        PaymentMethod $paymentMethod
    ): CheckoutOperationResult {
        $result = $this->flexPayClient->card()->createPayment(new CardPaymentRequest(
            amount: $session->amount(),
            currency: $session->currency(),
            merchantReference: $session->merchantReference(),
            description: $session->description(),
            callbackUrl: $session->callbackUrl(),
            approveUrl: $session->successUrl(),
            cancelUrl: $session->cancelUrl(),
            declineUrl: $session->cancelUrl(),
            homeUrl: $session->cancelUrl(),
            paymentMethod: $paymentMethod
        ));
        $checkoutUrl = $this->redirectValidator->validate($result->checkoutUrl);
        $session->setProviderObservation(
            NormalizedPaymentStatus::REQUIRES_ACTION,
            $result->rawStatus,
            $result->providerTransactionId,
            $checkoutUrl,
            $this->now(),
            $this->parseOccurredAt($result->occurredAt)
        );
        // La première sauvegarde garantit la persistance avant que l’action de redirection soit exposée.
        $this->transition($session, CheckoutState::REQUIRES_ACTION, 'provider');
        $this->transition($session, CheckoutState::REDIRECTING, 'application');

        return new CheckoutOperationResult(
            $this->publicView($session),
            CheckoutAction::redirect($checkoutUrl)
        );
    }

    private function refreshFromProvider(CheckoutSession $session): CheckoutOperationResult
    {
        $this->transition($session, CheckoutState::VERIFYING, 'application');

        try {
            $providerTransactionId = $session->providerTransactionId();
            if ($providerTransactionId === null) {
                return new CheckoutOperationResult($this->publicView($session), $this->pollAction($session));
            }
            $result = $session->selectedMethod() === PaymentMethod::CARD
                ? $this->flexPayClient->transactions()->getCardOrderStatus($providerTransactionId)
                : $this->flexPayClient->transactions()->getStatus(
                    $providerTransactionId,
                    $session->selectedMethod() ?? PaymentMethod::MOBILE_MONEY
                );
            $this->assertProviderResultMatchesSession($result, $session);
            $this->applyProviderResult($result, $session);

            return new CheckoutOperationResult(
                $this->publicView($session),
                $session->normalizedStatus()->isFinal() ? CheckoutAction::none() : $this->pollAction($session)
            );
        } catch (CheckoutException $exception) {
            $session->setProviderObservation(
                NormalizedPaymentStatus::UNKNOWN,
                $session->rawStatus(),
                $session->providerTransactionId(),
                null,
                $this->now()
            );
            $this->transition($session, CheckoutState::UNKNOWN, 'application');
            throw $exception;
        } catch (FlexPayException $exception) {
            $this->recordRefreshError($session, $exception);
            throw $exception;
        }
    }

    private function applyProviderResult(TransactionStatusResult $result, CheckoutSession $session): void
    {
        if (
            $session->state() === CheckoutState::SUCCESSFUL
            && $result->normalizedStatus !== NormalizedPaymentStatus::SUCCESSFUL
        ) {
            $this->logger->warning('Statut fournisseur ancien ignoré après succès.', [
                'checkout_id' => $session->id(),
                'raw_status' => $result->rawStatus,
            ]);
            return;
        }

        $session->setProviderObservation(
            $result->normalizedStatus,
            $result->rawStatus,
            $result->providerTransactionId,
            null,
            $this->now(),
            $this->parseOccurredAt($result->occurredAt)
        );
        $this->transition(
            $session,
            $this->statusMapper->toCheckoutState($result->normalizedStatus),
            'provider'
        );
    }

    private function transition(CheckoutSession $session, CheckoutState $to, string $source): void
    {
        if ($session->state() === $to) {
            return;
        }
        $this->stateMachine->assertAllowed($session->state(), $to);
        $expectedVersion = $session->version();
        $transition = $session->applyTransition($to, $this->now());
        $transition = new CheckoutTransition(
            $transition->checkoutId,
            $transition->from,
            $transition->to,
            $transition->occurredAt,
            $source,
            $transition->version
        );
        $this->store->save($session, $expectedVersion);
        $this->store->recordTransition($transition);
        $this->emit($this->eventNameFor($to), $session, $transition);
    }

    private function recordRefreshError(CheckoutSession $session, FlexPayException $exception): void
    {
        $expectedVersion = $session->version();
        $session->registerNetworkError($this->now());
        $target = $this->refreshPolicy->shouldBecomeUnknownAfterNetworkErrors($session)
            ? CheckoutState::UNKNOWN
            : CheckoutState::PENDING;
        $this->stateMachine->assertAllowed($session->state(), $target);
        $transition = $session->applyTransition($target, $this->now());
        $this->store->save($session, $expectedVersion);
        $this->store->recordTransition($transition);
        $this->emit($this->eventNameFor($target), $session, $transition);
        $this->logger->warning('Vérification FlexPay indisponible ; aucun échec n’est déclaré.', [
            'checkout_id' => $session->id(),
            'network_errors' => $session->consecutiveNetworkErrors(),
            'exception' => $exception::class,
        ]);
    }

    private function markInitializationUnknown(CheckoutSession $session, FlexPayException $exception): void
    {
        if ($session->state() === CheckoutState::INITIALIZING) {
            $session->setProviderObservation(
                NormalizedPaymentStatus::UNKNOWN,
                null,
                null,
                null,
                $this->now()
            );
            $this->transition($session, CheckoutState::UNKNOWN, 'application');
        }
        $this->logger->error('Initialisation FlexPay indéterminée ; aucun retry automatique.', [
            'checkout_id' => $session->id(),
            'ambiguous' => $exception->ambiguous,
            'exception' => $exception::class,
        ]);
    }

    private function load(string $checkoutId): CheckoutSession
    {
        $checkoutId = trim($checkoutId);
        $session = $checkoutId === '' ? null : $this->store->findById($checkoutId);
        if ($session === null) {
            throw new CheckoutNotFoundException($checkoutId);
        }
        if (
            $session->expiresAt() !== null
            && $session->expiresAt() <= $this->now()
            && !$session->state()->isTerminal()
        ) {
            $session->setProviderObservation(
                NormalizedPaymentStatus::EXPIRED,
                $session->rawStatus(),
                $session->providerTransactionId(),
                null,
                $this->now()
            );
            if ($this->stateMachine->canTransition($session->state(), CheckoutState::EXPIRED)) {
                $this->transition($session, CheckoutState::EXPIRED, 'application');
            }
        }

        return $session;
    }

    private function publicView(CheckoutSession $session): CheckoutPublicView
    {
        return CheckoutPublicView::fromSession(
            $session,
            $session->normalizedStatus()->isFinal()
                ? null
                : $this->refreshPolicy->nextPollAfterMilliseconds($session, $this->now())
        );
    }

    private function pollAction(CheckoutSession $session): CheckoutAction
    {
        $url = str_replace(
            '{checkoutId}',
            rawurlencode($session->id()),
            $this->configuration->statusPathTemplate
        );

        return CheckoutAction::poll(
            $url,
            $this->refreshPolicy->nextPollAfterMilliseconds($session, $this->now())
        );
    }

    private function findForWebhook(WebhookEvent $event): CheckoutSession
    {
        $session = $event->merchantReference !== null
            ? $this->store->findByMerchantReference($event->merchantReference)
            : null;
        if ($session === null && $event->providerTransactionId !== null) {
            $session = $this->store->findByProviderTransactionId($event->providerTransactionId);
        }
        if ($session === null) {
            throw new CheckoutNotFoundException($event->merchantReference ?? $event->providerTransactionId ?? '');
        }

        return $session;
    }

    private function assertWebhookMatchesSession(WebhookEvent $event, CheckoutSession $session): void
    {
        if ($event->merchantReference !== null && $event->merchantReference !== $session->merchantReference()) {
            throw new CheckoutConflictException('La référence du callback ne correspond pas.', 'reference_mismatch');
        }
        if ($event->amount !== null && abs($event->amount - $session->amount()) > 0.00001) {
            throw new CheckoutConflictException('Le montant du callback ne correspond pas.', 'amount_mismatch');
        }
        if (
            $event->currency !== null
            && trim($event->currency) !== ''
            && strtoupper($event->currency) !== $session->currency()
        ) {
            throw new CheckoutConflictException('La devise du callback ne correspond pas.', 'currency_mismatch');
        }
    }

    private function assertProviderResultMatchesSession(
        TransactionStatusResult $result,
        CheckoutSession $session
    ): void {
        if (
            $result->transactionReference !== ''
            && $result->transactionReference !== $session->merchantReference()
        ) {
            throw new CheckoutConflictException('La référence fournisseur ne correspond pas.', 'reference_mismatch');
        }
        if ($result->amount > 0 && abs($result->amount - $session->amount()) > 0.00001) {
            throw new CheckoutConflictException('Le montant fournisseur ne correspond pas.', 'amount_mismatch');
        }
        if ($result->currency !== '' && strtoupper($result->currency) !== $session->currency()) {
            throw new CheckoutConflictException('La devise fournisseur ne correspond pas.', 'currency_mismatch');
        }
    }

    private function isOlderProviderEvent(WebhookEvent $event, CheckoutSession $session): bool
    {
        $eventDate = $this->parseOccurredAt($event->occurredAt);
        return $eventDate !== null
            && $session->lastProviderOccurredAt() !== null
            && $eventDate < $session->lastProviderOccurredAt();
    }

    private function validateCreateRequest(CreateCheckoutRequest $request): void
    {
        if (trim($request->merchantReference) === '' || strlen($request->merchantReference) > 128) {
            throw new CheckoutException(
                'La référence marchande est vide ou trop longue.',
                'invalid_merchant_reference',
                'Paiement : la référence de transaction est invalide.'
            );
        }
        if ($request->amount <= 0 || preg_match('/^[A-Z]{3}$/', strtoupper(trim($request->currency))) !== 1) {
            throw new CheckoutException(
                'Le montant ou la devise du checkout est invalide.',
                'invalid_checkout_amount',
                'Paiement : le montant ou la devise est invalide.'
            );
        }
        if ($request->allowedMethods === []) {
            throw new CheckoutException(
                'Aucun moyen de paiement n’est autorisé.',
                'missing_payment_method',
                'Paiement : aucun moyen de paiement n’est disponible.'
            );
        }
        foreach ([$request->successUrl, $request->cancelUrl, $request->callbackUrl] as $url) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false
                || ($scheme !== 'https' && !($scheme === 'http' && $this->configuration->allowInsecureCheckoutUrls))
            ) {
                throw new CheckoutException(
                    'Une URL de retour ou de callback est invalide.',
                    'invalid_checkout_url',
                    'Paiement : la configuration des URLs est invalide.'
                );
            }
        }
    }

    private function callbackEventId(HandleCheckoutCallbackRequest $request): string
    {
        $query = $request->queryParameters;
        ksort($query);

        return 'cb_' . hash('sha256', $request->rawBody . '|' . (string) json_encode($query));
    }

    private function eventNameFor(CheckoutState $state): string
    {
        return match ($state) {
            CheckoutState::PENDING => 'PaymentPending',
            CheckoutState::REQUIRES_ACTION, CheckoutState::REDIRECTING => 'PaymentRequiresAction',
            CheckoutState::SUCCESSFUL => 'PaymentSucceeded',
            CheckoutState::FAILED => 'PaymentFailed',
            CheckoutState::CANCELLED => 'PaymentCancelled',
            CheckoutState::EXPIRED => 'PaymentExpired',
            CheckoutState::UNKNOWN => 'PaymentStatusUnknown',
            CheckoutState::INITIALIZING => 'PaymentInitialized',
            default => 'CheckoutStateChanged',
        };
    }

    private function emit(
        string $name,
        CheckoutSession $session,
        ?CheckoutTransition $transition
    ): void {
        if ($this->eventListener === null) {
            return;
        }
        try {
            $this->eventListener->handle(new CheckoutEvent(
                $name,
                CheckoutPublicView::fromSession($session),
                $this->now(),
                $transition
            ));
        } catch (\Throwable $exception) {
            // La transition est déjà persistée : l’application rejouera ses effets métier séparément.
            $this->logger->error('Le listener checkout a échoué après persistance.', [
                'checkout_id' => $session->id(),
                'event' => $name,
                'exception' => $exception::class,
            ]);
        }
    }

    private function newCheckoutId(): string
    {
        return 'chk_' . bin2hex(random_bytes(16));
    }

    private function parseOccurredAt(?string $occurredAt): ?DateTimeImmutable
    {
        if ($occurredAt === null || trim($occurredAt) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($occurredAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function now(): DateTimeImmutable
    {
        return ($this->clock)();
    }

    /**
     * @param list<PaymentMethod> $methods
     * @return list<PaymentMethod>
     */
    private function uniqueMethods(array $methods): array
    {
        $unique = [];
        foreach ($methods as $method) {
            $unique[$method->value] = $method;
        }

        return array_values($unique);
    }
}
