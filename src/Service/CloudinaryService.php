<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;
    private bool $configured = false;

    public function __construct(string $cloudinaryUrl, LoggerInterface $logger)
    {
        // Normaliser la valeur d'env (Render peut injecter avec espaces/guillemets).
        $normalizedUrl = trim($cloudinaryUrl, " \t\n\r\0\x0B\"'");

        // Vérifier si CLOUDINARY_URL est bien défini et valide
        if ($normalizedUrl !== '' && str_starts_with($normalizedUrl, 'cloudinary://')) {
            try {
                $this->initializeFromUrl($normalizedUrl, $logger);
            } catch (\Throwable $e) {
                $logger->error('CloudinaryService initialization failed', [
                    'exception' => $e->getMessage(),
                ]);
                $this->configured = false;
            }
        } else {
            $logger->warning('CloudinaryService: CLOUDINARY_URL not configured', [
                'provided_url' => $normalizedUrl === '' ? 'EMPTY' : 'INVALID_FORMAT',
            ]);
        }
    }

    /**
     * Initialize Cloudinary from URL string
     */
    private function initializeFromUrl(string $normalizedUrl, LoggerInterface $logger): void
    {
        // Parse the URL: cloudinary://api_key:api_secret@cloud_name
        $parsed = parse_url($normalizedUrl);
        if (!$parsed || !isset($parsed['host'])) {
            throw new \Exception('Invalid Cloudinary URL format');
        }

        $config = [
            'cloud_name' => $parsed['host'],
            'api_key' => $parsed['user'] ?? '',
            'api_secret' => $parsed['pass'] ?? '',
            'secure' => true,
        ];

        $this->cloudinary = new Cloudinary($config);
        $this->configured = true;
        $logger->info('CloudinaryService initialized successfully', [
            'cloud_name' => $parsed['host'],
        ]);
    }

    /**
     * Check if Cloudinary is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Upload a file to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder Cloudinary folder path (e.g., 'menus')
     * @return array {'public_id': 'menus/xxx', 'secure_url': 'https://...', ...}
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $folder = 'menus'): array
    {
        if (!$this->configured || !$this->cloudinary) {
            throw new \Exception('Cloudinary is not configured. Please set CLOUDINARY_URL environment variable on your server.');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file format. Allowed: JPEG, PNG, WebP, GIF');
        }

        if ($file->getSize() > 5242880) { // 5 MB
            throw new \Exception('File size exceeds 5MB limit');
        }

        $tempPath = $file->getPathname();
        
        $result = $this->cloudinary->uploadApi()->upload($tempPath, [
            'folder' => $folder,
            'resource_type' => 'auto',
            'quality' => 'auto',
        ]);

        // Convert ApiResponse object to array
        return json_decode(json_encode($result), true) ?? [];
    }

    /**
     * Delete a file from Cloudinary by public_id
     */
    public function delete(string $publicId): void
    {
        if (!$this->configured || !$this->cloudinary) {
            return; // Silently fail if not configured
        }

        $this->cloudinary->uploadApi()->destroy($publicId);
    }
}
