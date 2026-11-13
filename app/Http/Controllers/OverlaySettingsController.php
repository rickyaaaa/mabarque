<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverlaySettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->overlaySetting]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_scale' => ['required', 'integer', 'between:80,130'],
            'max_players' => ['required', 'integer', 'between:3,10'],
            'show_now_playing' => ['required', 'boolean'],
            'transparent_mode' => ['required', 'boolean'],
        ]);

        $settings = $request->user()->overlaySetting()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json(['data' => $settings->fresh()]);
    }
}
