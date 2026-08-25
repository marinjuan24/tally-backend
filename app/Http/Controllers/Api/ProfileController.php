<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                'photo'          => $user->photo ? Storage::disk('public')->url($user->photo) : null,
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
     * Serve user profile photo.
     */
    public function showPhoto(Request $request): Response
    {
        $user = $request->user();

        if (!$user->photo) {
            abort(404, 'No hay foto de perfil');
        }

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
     * Upload user profile photo.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = $request->user();

        // Delete old photo if exists
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('photos', 'public');
        $user->update(['photo' => $path]);

        return response()->json([
            'message' => 'Foto actualizada',
            'photo'   => Storage::disk('public')->url($path),
        ]);
    }
}
