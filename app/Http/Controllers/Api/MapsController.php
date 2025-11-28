<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapsController extends ApiController
{
    public function createPin(Request $request)
    {
        // Accept either address or lat+lng
        $data = $request->validate([
            'address' => 'sometimes|string|max:255',
            'lat' => 'required_without:address|numeric',
            'lng' => 'required_without:address|numeric',
            'label' => 'sometimes|string|max:64',
            'zoom' => 'sometimes|integer|min:0|max:21',
            'width' => 'sometimes|integer|min:1|max:2048',
            'height' => 'sometimes|integer|min:1|max:2048',
        ]);

        $key = env('GOOGLE_MAPS_API_KEY');

        if (! $key) {
            return $this->error('GOOGLE_MAPS_API_KEY not set', 500);
        }

        // If address is present, geocode it to lat/lng
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if (! empty($data['address'])) {
            // Ensure we have an API key
            if (! $key) {
                return $this->error('GOOGLE_MAPS_API_KEY not set', 500);
            }

            $address = $data['address'];
            $geoResp = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $key,
            ]);

            if (! $geoResp->ok()) {
                return $this->error('Failed to geocode address', 500);
            }

            $geoJson = $geoResp->json();
            if (empty($geoJson['results']) || ($geoJson['status'] ?? '') !== 'OK') {
                return $this->error('Unable to geocode the provided address', 422);
            }

            $location = $geoJson['results'][0]['geometry']['location'];
            $lat = $location['lat'];
            $lng = $location['lng'];
        }

        // Build a static map URL with a pin. Using Google Static Maps API as an example.
        $zoom = $data['zoom'] ?? 14;
        $size = ($data['width'] ?? 600) . 'x' . ($data['height'] ?? 300);
        $label = isset($data['label']) ? urlencode($data['label']) : '';

        $pin = "&markers=color:red|label:{$label}|{$lat},{$lng}";

        $url = "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size={$size}{$pin}&key={$key}";

        return $this->success(['map_url' => $url], 'Static map URL with pin');
    }
}
