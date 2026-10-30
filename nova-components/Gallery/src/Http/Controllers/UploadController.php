<?php

namespace Ultrasimplified\Gallery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ultrasimplified\Gallery\Gallery;
use Ultrasimplified\Gallery\ImageStore;

class UploadController
{
    /**
     * Store one image for a gallery field and say where it went.
     */
    public function __invoke(Request $request, ImageStore $store): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'token' => ['required', 'string'],
        ]);

        $settings = Gallery::settingsFromToken($validated['token']);

        abort_if($settings === null, 403);

        return response()->json($store->store($request->file('image'), $settings));
    }
}
