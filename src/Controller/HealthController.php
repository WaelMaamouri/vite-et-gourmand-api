<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/healthz/env', name: 'healthz_env', methods: ['GET'])]
    public function envDiagnostics(): JsonResponse
    {
        $cloudinaryUrl = $_ENV['CLOUDINARY_URL'] ?? '';
        $normalized = trim($cloudinaryUrl, " \t\n\r\0\x0B\"'");

        return new JsonResponse([
            'cloudinary_url_raw' => $cloudinaryUrl,
            'cloudinary_url_normalized' => $normalized,
            'has_cloudinary' => !empty($cloudinaryUrl),
            'is_valid_format' => str_starts_with($normalized, 'cloudinary://'),
            'database_configured' => !empty($_ENV['DATABASE_URL'] ?? ''),
            'app_env' => $_ENV['APP_ENV'] ?? 'NOT_SET',
        ]);
    }
}
