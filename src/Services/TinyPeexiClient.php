<?php

namespace Tekkenking\TinyPeexi\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tekkenking\TinyPeexi\DTOs\AssetDto;
use Tekkenking\TinyPeexi\Exceptions\TinyPeexiException;

class TinyPeexiClient
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a configured HTTP client instance ready to talk to aipeexi.
     */
    protected function client(): PendingRequest
    {
        $apiUrl = rtrim($this->config['api_url'] ?? 'http://localhost:8080', '/');
        $apiKey = $this->config['api_key'] ?? '';
        $timeout = $this->config['advanced']['timeout'] ?? 10;
        $retries = $this->config['advanced']['retries'] ?? 3;
        $retryDelay = $this->config['advanced']['retry_delay'] ?? 500;

        $client = Http::baseUrl($apiUrl)
            ->timeout($timeout)
            ->withToken($apiKey)
            ->acceptJson();

        if ($retries > 0) {
            $client->retry($retries, $retryDelay);
        }

        return $client;
    }

    /**
     * Upload an asset to the Lossless Media Service.
     *
     * @param string|UploadedFile $file Path to file or Laravel UploadedFile instance
     * @return AssetDto
     * @throws TinyPeexiException
     */
    public function upload(string|UploadedFile $file): AssetDto
    {
        $name = 'upload';
        $contents = '';
        $filename = 'image.jpg';

        if ($file instanceof UploadedFile) {
            $contents = file_get_contents($file->getRealPath());
            $filename = $file->getClientOriginalName();
        } else {
            if (!file_exists($file)) {
                throw new TinyPeexiException("File not found at path: {$file}");
            }
            $contents = file_get_contents($file);
            $filename = basename($file);
        }

        $response = $this->client()
            ->attach('file', $contents, $filename)
            ->post('/v1/assets');

        if ($response->failed()) {
            throw new TinyPeexiException('Upload failed: ' . $response->body(), $response->status());
        }

        $data = $response->json();

        // The Rust API currently returns an array of objects when uploading
        // Ensure we handle both single object and array return formats safely.
        $assetData = isset($data[0]) ? $data[0] : $data;

        return new AssetDto($assetData['sha256'] ?? $assetData['sha']);
    }

    /**
     * Upload multiple assets to the Lossless Media Service in a single request.
     *
     * @param array<string|UploadedFile>|string|UploadedFile $files Array of files, or a single file/UploadedFile
     * @return array<AssetDto>
     * @throws TinyPeexiException
     */
    public function uploadMany(array|string|UploadedFile $files): array
    {
        $client = $this->client();

        if (!is_array($files)) {
            $files = [$files];
        }

        if (empty($files)) {
            return [];
        }

        foreach ($files as $index => $file) {
            $contents = '';
            $filename = "image_{$index}.jpg";

            if ($file instanceof UploadedFile) {
                $contents = file_get_contents($file->getRealPath());
                $filename = $file->getClientOriginalName();
            } else {
                if (!file_exists($file)) {
                    throw new TinyPeexiException("File not found at path: {$file}");
                }
                $contents = file_get_contents($file);
                $filename = basename($file);
            }

            // Using 'files[]' as the field name which the Rust backend supports.
            // In Laravel's Http client, calling attach() multiple times with the exact same
            // key ('files[]') will simply overwrite the previous one.
            // To send an array of files, we must make the key unique but still parsable as an array by the backend
            // Note: The Rust rocket/axum backend using multipart standard will parse fields named 'files[]'
            // even if we just use the name `files[]`. To bypass Laravel's overwriting, we don't use 'files[]' 
            // as the dictionary key directly, but we can pass an array of files to attach, or we can use curl directly if Guzzle acts up.
            // Actually, Guzzle supports multiple values for the same name if we pass them as separate multi-part elements.
            // But Laravel's `attach` method uses the "name" as the array key in its internal `$pendingFiles` array!
            // To fix this, we can use a unique name, or fallback to the underlying `withBody` if needed, 
            // but the cleanest and fully supported way in Laravel 9+ is to construct the multipart array manually.

            // The simplest workaround in Laravel's wrapper is to just append a space or specific index 
            // to the name internally if it overrides, but the best way is to interact with Guzzle's `multipart` directly or use indexing.
            $client->attach("files[{$index}]", $contents, $filename);
        }

        $response = $client->post('/v1/assets');

        if ($response->failed()) {
            throw new TinyPeexiException('Batch upload failed: ' . $response->body(), $response->status());
        }

        $data = $response->json();
        $assets = [];

        // The backend returns an array of uploaded asset objects
        if (is_array($data)) {
            foreach ($data as $assetData) {
                $assets[] = new AssetDto($assetData['sha256'] ?? $assetData['sha']);
            }
        }

        return $assets;
    }

    /**
     * Delete an asset from the system.
     *
     * @param string $sha
     * @return bool
     * @throws TinyPeexiException
     */
    public function delete(string $sha): bool
    {
        $response = $this->client()->delete("/v1/assets/{$sha}");

        if ($response->failed()) {
            throw new TinyPeexiException('Delete failed: ' . $response->body(), $response->status());
        }

        return true;
    }

    /**
     * Start building a variant for a specific asset.
     *
     * @param string $sha The original asset's SHA
     * @return VariantBuilder
     */
    public function variant(string $sha): VariantBuilder
    {
        return new VariantBuilder($this, $sha, $this->config);
    }

    /**
     * Get the raw configuration array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Internal method to dispatch the variant generation request.
     * Called by the VariantBuilder.
     */
    public function requestVariant(array $payload): void
    {
        // The backend uses a Query extractor in Rust which expects all parameters 
        // (sha, w, h, format, q, etc.) to be in the URL query string, not the JSON body.
        $queryString = http_build_query($payload);
        $response = $this->client()->post("/v1/variants?{$queryString}");

        if ($response->failed()) {
            throw new TinyPeexiException('Variant generation failed: ' . $response->body(), $response->status());
        }
    }

    /**
     * Internal method to dispatch a batch variant generation request.
     * Called by the VariantBuilder.
     */
    public function requestBatchVariant(array $payload): void
    {
        $queryString = http_build_query($payload);
        $response = $this->client()->post("/v1/variants/batch?{$queryString}");

        if ($response->failed()) {
            throw new TinyPeexiException('Batch variant generation failed: ' . $response->body(), $response->status());
        }
    }
}
