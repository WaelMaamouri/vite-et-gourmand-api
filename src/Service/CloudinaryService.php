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
                $this->cloudinary = new Cloudinary([
                    'secure' => true,
                    'url' => $normalizedUrl,
                ]);
                $this->configured = true;
                $logger->info('CloudinaryService initialized successfully');
            } catch (\Throwable $e) {
                // Si la création échoue, laisser $cloudinary à null
                $logger->error('CloudinaryService initialization failed', [
                    'exception' => $e->getMessage(),
                    'url_prefix' => substr($normalizedUrl, 0, 20) . '...',
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

        return $result;
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
