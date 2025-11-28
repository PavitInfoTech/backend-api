<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class MapsController extends ApiController
{
    public function createPin(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'label' => 'sometimes|string|max:64',
            'zoom' => 'sometimes|integer|min:0|max:21',
            'width' => 'sometimes|integer|min:1|max:2048',
            'height' => 'sometimes|integer|min:1|max:2048',
        ]);

        $key = env('GOOGLE_MAPS_API_KEY');

        if (! $key) {
            return $this->error('GOOGLE_MAPS_API_KEY not set', 500);
        }

        // Build a static map URL with a pin. Using Google Static Maps API as an example.
        $zoom = $data['zoom'] ?? 14;
        $size = ($data['width'] ?? 600) . 'x' . ($data['height'] ?? 300);
        $label = isset($data['label']) ? urlencode($data['label']) : '';

        $pin = "&markers=color:red|label:{$label}|{$data['lat']},{$data['lng']}";

        $url = "https://maps.googleapis.com/maps/api/staticmap?center={$data['lat']},{$data['lng']}&zoom={$zoom}&size={$size}{$pin}&key={$key}";

        return $this->success(['map_url' => $url], 'Static map URL with pin');
    }
}
