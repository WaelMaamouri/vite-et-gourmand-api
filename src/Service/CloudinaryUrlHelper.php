<?php

namespace App\Service;

class CloudinaryUrlHelper
{
    private string $cloudinaryUrl;

    public function __construct(string $cloudinaryUrl)
    {
        $this->cloudinaryUrl = $cloudinaryUrl;
        // Extract cloud name from URL format: cloudinary://api_key:api_secret@cloud_name
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
            $cloudName = 'dw7arnugc';
            return "https://res.cloudinary.com/{$cloudName}/image/upload/{$imageIdentifier}";
        }

        // Otherwise, assume it's a local filesystem path and return it as-is for backward compatibility
        return $imageIdentifier;
    }
}
