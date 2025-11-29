<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class MapsController extends ApiController
{
    /**
     * Generate Google Maps embed URL and direct link for a given address.
     *
     * Returns both an iframe-ready embed URL and a direct Google Maps link
     * that can be opened in browser or app.
     */
    public function createPin(Request $request)
    {
        $data = $request->validate([
            'address' => 'required|string|max:500',
            'zoom' => 'sometimes|integer|min:0|max:21',
            'width' => 'sometimes|integer|min:1|max:2048',
            'height' => 'sometimes|integer|min:1|max:2048',
            'map_type' => 'sometimes|string|in:roadmap,satellite',
        ]);

        $key = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY'));

        if (! $key) {
            return $this->error('GOOGLE_MAPS_API_KEY not set', 500);
        }

        $address = $data['address'];
        $encodedAddress = urlencode($address);
        $zoom = $data['zoom'] ?? 15;
        $mapType = $data['map_type'] ?? 'roadmap';
        $width = $data['width'] ?? 600;
        $height = $data['height'] ?? 450;

        // Google Maps Embed API URL (for iframe embedding)
        // Uses "place" mode to show a pin at the address
        $embedUrl = "https://www.google.com/maps/embed/v1/place"
            . "?key={$key}"
            . "&q={$encodedAddress}"
            . "&zoom={$zoom}"
            . "&maptype={$mapType}";

        // Direct Google Maps link (opens in browser/app)
        $mapsLink = "https://www.google.com/maps/search/?api=1&query={$encodedAddress}";

        // Pre-built iframe HTML for convenience
        $iframe = '<iframe '
            . 'width="' . $width . '" '
            . 'height="' . $height . '" '
            . 'style="border:0" '
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
        ], 'Google Maps URLs generated');
    }
}
