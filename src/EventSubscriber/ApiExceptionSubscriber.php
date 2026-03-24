<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Réponses JSON cohérentes sur /api et log des erreurs non HTTP (ex. PDO, schéma BDD).
 * Ne remplace pas les HttpException (404, 403…) déjà gérées par Symfony.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après ErrorListener (-128) : remplacer la page HTML 500 par du JSON sur /api.
        return [KernelEvents::EXCEPTION => ['onKernelException', -256]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof HttpExceptionInterface) {
            return;
        }

        $this->logger->error('API exception (non-HTTP): ' . $e->getMessage(), [
            'exception' => $e,
            'path' => $event->getRequest()->getPathInfo(),
        ]);

        $body = [
            'message' => 'Service temporairement indisponible (base de données ou configuration).',
        ];
        if ($this->debug) {
            $body['detail'] = $e->getMessage();
        }

        $event->setResponse(new JsonResponse($body, 503));
    }
}
