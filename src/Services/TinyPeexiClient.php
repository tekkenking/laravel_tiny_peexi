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

        return new AssetDto($data['sha256'] ?? $data['sha']);
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
        $response = $this->client()->post('/v1/variants', $payload);

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
        $response = $this->client()->post('/v1/variants/batch', $payload);

        if ($response->failed()) {
            throw new TinyPeexiException('Batch variant generation failed: ' . $response->body(), $response->status());
        }
    }
}
