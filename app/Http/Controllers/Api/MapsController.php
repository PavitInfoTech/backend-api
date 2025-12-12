<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class MapsController extends ApiController
{
    /**
     * Generate Google Maps embed URL and direct link for a given address.
     *
     * Returns:
     * - Google Maps embed URL (works without API key)
     * - Google Maps direct link (opens in browser/app)
     * - Ready-to-use iframe HTML
     */
    public function createPin(Request $request)
    {
        $data = $request->validate([
            'address' => 'required|string|max:500',
            'zoom' => 'sometimes|integer|min:1|max:21',
            'width' => 'sometimes|integer|min:1|max:2048',
            'height' => 'sometimes|integer|min:1|max:2048',
        ]);

        $address = $data['address'];
        $encodedAddress = urlencode($address);
        $zoom = $data['zoom'] ?? 15;
        $width = $data['width'] ?? 600;
        $height = $data['height'] ?? 450;

        // Google Maps embed URL (no API key required)
        $embedUrl = "https://maps.google.com/maps?q={$encodedAddress}&z={$zoom}&output=embed";

        // Google Maps direct link (opens in browser/app)
        $mapsLink = "https://www.google.com/maps/search/?api=1&query={$encodedAddress}";

        // Pre-built iframe HTML
        $iframe = '<iframe '
            . 'width="' . $width . '" '
            . 'height="' . $height . '" '
            . 'style="border:0; width:100%" '
            . 'loading="lazy" '
            . 'allowfullscreen '
            . 'referrerpolicy="no-referrer-when-downgrade" '
            . 'src="' . htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '</iframe>';

        return $this->success([
            'embed_url' => $embedUrl,
            'maps_link' => $mapsLink,
            'iframe' => $iframe,
            'address' => $address,
            'zoom' => $zoom,
        ], 'Map URLs generated');
    }
}
