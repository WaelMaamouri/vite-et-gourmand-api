<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;
    private bool $configured = false;

    public function __construct(string $cloudinaryUrl)
    {
        // Vérifier si CLOUDINARY_URL est bien défini et valide
        if (!empty($cloudinaryUrl) && str_starts_with($cloudinaryUrl, 'cloudinary://')) {
            try {
                $this->cloudinary = new Cloudinary([
                    'secure' => true,
                    'url' => $cloudinaryUrl,
                ]);
                $this->configured = true;
            } catch (\Throwable $e) {
                // Si la création échoue, laisser $cloudinary à null
                $this->configured = false;
            }
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
