<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImgbbService
{
    private string $apiKey;
    private string $uploadUrl = 'https://api.imgbb.com/1/upload';

    public function __construct()
    {
        $this->apiKey = config('services.imgbb.api_key');
    }

    /**
     * Upload an image file to imgBB.
     *
     * @param string $filePath Absolute path to the image file
     * @param string|null $name Optional name for the image
     * @return string The direct URL of the uploaded image
     * @throws \Exception
     */
    public function uploadFile(string $filePath, ?string $name = null): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        if (empty($this->apiKey)) {
            throw new \Exception('IMGBB_API_KEY is not configured');
        }

        $filename = basename($filePath);

        $response = Http::timeout(30)
            ->attach('image', file_get_contents($filePath), $filename)
            ->post($this->uploadUrl . '?key=' . $this->apiKey);

        if ($response->failed()) {
            Log::error('imgBB upload failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Error al subir imagen a imgBB: ' . $response->body());
        }

        $data = $response->json('data');

        if (!$data || empty($data['url'])) {
            throw new \Exception('Respuesta inválida de imgBB');
        }

        return $data['url'];
    }

    /**
     * Upload base64-encoded image data to imgBB.
     *
     * @param string $base64Data Base64-encoded image data
     * @param string|null $name Optional name for the image
     * @return string The direct URL of the uploaded image
     * @throws \Exception
     */
    public function uploadBase64(string $base64Data, ?string $name = null): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('IMGBB_API_KEY is not configured');
        }

        $response = Http::timeout(30)
            ->post($this->uploadUrl . '?key=' . $this->apiKey, [
                'image' => $base64Data,
                'name'  => $name,
            ]);

        if ($response->failed()) {
            Log::error('imgBB upload failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Error al subir imagen a imgBB: ' . $response->body());
        }

        $data = $response->json('data');

        if (!$data || empty($data['url'])) {
            throw new \Exception('Respuesta inválida de imgBB');
        }

        return $data['url'];
    }
}
