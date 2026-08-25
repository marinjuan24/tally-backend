<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImgbbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get user profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('account.cards');

        return response()->json([
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'photo'          => $user->photo,
                'account_number' => $user->account_number,
                'account'        => $user->account,
            ],
        ]);
    }

    /**
     * Update user profile (name, phone).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado',
            'user'    => $request->user()->only('id', 'name', 'email', 'phone', 'account_number'),
        ]);
    }

    /**
     * Upload user profile photo to imgBB.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = $request->user();

        try {
            $imgbb = new ImgbbService();

            // Upload to imgBB
            $imageUrl = $imgbb->uploadFile(
                $request->file('photo')->getRealPath(),
                'profile_' . $user->id
            );

            // Delete old photo from imgBB if it was hosted there
            if ($user->photo && str_contains($user->photo, 'ibb.co')) {
                // imgBB delete URLs require visiting the page, we can't easily delete via API
                // The old photo will eventually expire or can be cleaned up manually
            }

            // Store the imgBB URL directly in the user record
            $user->update(['photo' => $imageUrl]);

            return response()->json([
                'message' => 'Foto actualizada',
                'photo'   => $imageUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading photo to imgBB', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al subir la foto. Intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Serve user profile photo (legacy - for local storage photos).
     */
    public function showPhoto(Request $request): Response
    {
        $user = $request->user();

        if (!$user->photo) {
            abort(404, 'No hay foto de perfil');
        }

        // If the photo is an imgBB URL, redirect to it
        if (str_contains($user->photo, 'ibb.co') || filter_var($user->photo, FILTER_VALIDATE_URL)) {
            return redirect($user->photo);
        }

        // Legacy: serve from local storage
        $path = Storage::disk('public')->path($user->photo);

        if (!file_exists($path)) {
            abort(404, 'Archivo de foto no encontrado');
        }

        $mime = mime_content_type($path);
        $content = file_get_contents($path);

        return response($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'public, max-age=86400',
        ]);
    }

    /**
     * Serve photo by filename (legacy route - for local storage photos).
     */
    public function servePhoto(string $filename): Response
    {
        $filename = basename($filename);
        if ($filename === '.' || $filename === '..' || !preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $filename)) {
            abort(400, 'Nombre de archivo inválido');
        }

        $path = storage_path('app/public/photos/' . $filename);

        if (!file_exists($path)) {
            abort(404, 'Foto no encontrada');
        }

        $mime = mime_content_type($path);

        if ($mime === false) {
            $mime = 'application/octet-stream';
        }

        $content = file_get_contents($path);

        if ($content === false) {
            abort(500, 'Error al leer la foto');
        }

        return response($content, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
