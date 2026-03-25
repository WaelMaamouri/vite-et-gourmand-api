<?php

namespace App\Service;

class CloudinaryUrlHelper
{
    private string $cloudName = 'dw7arnugc';

    public function __construct(string $cloudinaryUrl)
    {
        $normalizedUrl = trim($cloudinaryUrl, " \t\n\r\0\x0B\"'");

        // Extract cloud name from URL format: cloudinary://api_key:api_secret@cloud_name
        $host = parse_url($normalizedUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $this->cloudName = $host;
        }
    }

    public function getImageUrl(?string $imageIdentifier): ?string
    {
        if (!$imageIdentifier) {
            return null;
        }

        // If it already looks like a full URL, return it as-is
        if (str_starts_with($imageIdentifier, 'http://') || str_starts_with($imageIdentifier, 'https://')) {
            return $imageIdentifier;
        }

        // If it looks like a Cloudinary ID (e.g., menus/abc123), construct Cloudinary URL
        if (str_contains($imageIdentifier, '/')) {
            return "https://res.cloudinary.com/{$this->cloudName}/image/upload/{$imageIdentifier}";
        }

        // Otherwise, assume it's a local filesystem path and return it as-is for backward compatibility
        return $imageIdentifier;
    }
}
