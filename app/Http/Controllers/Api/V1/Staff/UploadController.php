<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Unggah foto menu. Hasilnya (path / url) dikirim balik ke endpoint produk lewat "image_url".
     * Perlu `php artisan storage:link` supaya file bisa diakses publik.
     */
    public function productImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'image.max' => 'Ukuran foto maksimal 2 MB.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
        ]);

        $stored = $request->file('image')->store('products', 'public');
        $path = 'storage/'.$stored;

        return ApiResponse::success([
            'path' => $path,
            'url' => asset($path),
        ], 'Foto diunggah.', 201);
    }
}
