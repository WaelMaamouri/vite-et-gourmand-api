<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    /**
     * GET /api/debug/env
     * Temporary endpoint to verify environment variables are loaded correctly.
     * Remove this controller after debugging.
     */
    #[Route('/env', name: 'api_debug_env', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function env(): JsonResponse
    {
        return $this->json([
            'app_env' => $_ENV['APP_ENV'] ?? 'NOT_SET',
            'app_debug' => $_ENV['APP_DEBUG'] ?? 'NOT_SET',
            'cloudinary_url_raw' => $_ENV['CLOUDINARY_URL'] ?? 'NOT_SET',
            'cloudinary_url_normalized' => $this->normalizeCloudinaryUrl($_ENV['CLOUDINARY_URL'] ?? ''),
            'database_url_prefix' => preg_replace('/:.*@/', ':***@', $_ENV['DATABASE_URL'] ?? 'NOT_SET'),
            'has_cloudinary' => !empty($_ENV['CLOUDINARY_URL']),
        ]);
    }

    private function normalizeCloudinaryUrl(string $url): string
    {
        $normalized = trim($url, " \t\n\r\0\x0B\"'");
        return [
            'value' => $normalized,
            'is_valid' => str_starts_with($normalized, 'cloudinary://'),
            'length' => strlen($normalized),
        ];
    }
}
