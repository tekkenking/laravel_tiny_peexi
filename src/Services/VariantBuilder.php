<?php

namespace Tekkenking\TinyPeexi\Services;

use Tekkenking\TinyPeexi\DTOs\VariantDto;
use Tekkenking\TinyPeexi\Exceptions\TinyPeexiException;

/**
 * Fluent builder for creating image variants.
 *
 * Usage:
 *   TinyPeexi::variant($sha)
 *       ->resize(800)
 *       ->format('webp')
 *       ->quality(85)
 *       ->generate();
 */
class VariantBuilder
{
    protected TinyPeexiClient $client;
    protected string $sha;
    protected array $config;
    protected array $params = [];

    public function __construct(TinyPeexiClient $client, string $sha, array $config)
    {
        $this->client = $client;
        $this->sha = $sha;
        $this->config = $config;
    }

    // =========================================================================
    // DIMENSIONS
    // =========================================================================

    /**
     * Set the target width in pixels.
     *
     * @param int $width Target width (1-10000)
     */
    public function width(int $width): static
    {
        $this->params['w'] = $width;
        return $this;
    }

    /**
     * Set the target height in pixels.
     *
     * @param int $height Target height (1-10000)
     */
    public function height(int $height): static
    {
        $this->params['h'] = $height;
        return $this;
    }

    /**
     * Shortcut to set width (and optionally height).
     *
     * @param int $width
     * @param int|null $height
     */
    public function resize(int $width, ?int $height = null): static
    {
        $this->params['w'] = $width;
        if ($height !== null) {
            $this->params['h'] = $height;
        }
        return $this;
    }

    /**
     * Set how the image fits within target dimensions.
     *
     * Supported: 'cover', 'contain', 'fill', 'inside', 'outside'
     *
     * @param string $mode
     */
    public function fit(string $mode): static
    {
        $this->params['fit'] = $mode;
        return $this;
    }

    /**
     * Set device pixel ratio multiplier (1.0 - 4.0).
     *
     * @param float $dpr
     */
    public function dpr(float $dpr): static
    {
        $this->params['dpr'] = $dpr;
        return $this;
    }

    // =========================================================================
    // OUTPUT FORMAT
    // =========================================================================

    /**
     * Set the output format.
     *
     * Supported: 'jpeg', 'png', 'webp', 'avif', 'gif', 'tiff', 'ico', 'svg'
     *
     * @param string $format
     */
    public function format(string $format): static
    {
        $this->params['format'] = strtolower($format);
        return $this;
    }

    /**
     * Set compression quality (1-100).
     *
     * @param int $quality
     */
    public function quality(int $quality): static
    {
        $this->params['q'] = $quality;
        return $this;
    }

    /**
     * Enable progressive JPEG encoding.
     */
    public function progressive(bool $enabled = true): static
    {
        $this->params['progressive'] = $enabled;
        return $this;
    }

    /**
     * Strip EXIF metadata from the output.
     */
    public function stripMetadata(bool $strip = true): static
    {
        $this->params['strip_metadata'] = $strip;
        return $this;
    }

    // =========================================================================
    // CROPPING
    // =========================================================================

    /**
     * Set the crop anchor position.
     *
     * Supported: 'center', 'top', 'bottom', 'left', 'right',
     *            'topleft', 'topright', 'bottomleft', 'bottomright',
     *            'face', 'entropy', 'attention'
     *
     * @param string $position
     */
    public function crop(string $position): static
    {
        $this->params['crop'] = strtolower($position);
        return $this;
    }

    /**
     * Set a manual crop rectangle.
     *
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     */
    public function cropRect(int $x, int $y, int $width, int $height): static
    {
        $this->params['crop_rect'] = [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];
        return $this;
    }

    /**
     * Auto-trim whitespace borders from the image.
     *
     * @param int|null $threshold Trim color tolerance (0-255)
     */
    public function trim(?int $threshold = null): static
    {
        $this->params['trim'] = true;
        if ($threshold !== null) {
            $this->params['trim_threshold'] = $threshold;
        }
        return $this;
    }

    // =========================================================================
    // ORIENTATION
    // =========================================================================

    /**
     * Rotate the image.
     *
     * Supported: 0, 90, 180, 270, -90
     *
     * @param int $degrees
     */
    public function rotate(int $degrees): static
    {
        $this->params['rotate'] = $degrees;
        return $this;
    }

    /**
     * Flip the image.
     *
     * Supported: 'h' (horizontal), 'v' (vertical), 'both'
     *
     * @param string $direction
     */
    public function flip(string $direction): static
    {
        $this->params['flip'] = strtolower($direction);
        return $this;
    }

    /**
     * Auto-fix EXIF orientation.
     */
    public function autoOrient(bool $enabled = true): static
    {
        $this->params['auto_orient'] = $enabled;
        return $this;
    }

    // =========================================================================
    // EFFECTS
    // =========================================================================

    /**
     * Apply Gaussian blur.
     *
     * @param float $sigma Blur sigma (0.3 - 1000.0)
     */
    public function blur(float $sigma): static
    {
        $this->params['blur'] = $sigma;
        return $this;
    }

    /**
     * Apply sharpening.
     *
     * @param float $amount Sharpen amount (0.5 - 10.0)
     */
    public function sharpen(float $amount): static
    {
        $this->params['sharpen'] = $amount;
        return $this;
    }

    /**
     * Adjust brightness.
     *
     * @param float $value 0.1 = dark, 1.0 = normal, 10.0 = bright
     */
    public function brightness(float $value): static
    {
        $this->params['brightness'] = $value;
        return $this;
    }

    /**
     * Adjust contrast.
     *
     * @param float $value 0.1 = low, 1.0 = normal, 10.0 = high
     */
    public function contrast(float $value): static
    {
        $this->params['contrast'] = $value;
        return $this;
    }

    /**
     * Adjust saturation.
     *
     * @param float $value 0.0 = grayscale, 1.0 = normal, 2.0 = vivid
     */
    public function saturation(float $value): static
    {
        $this->params['saturation'] = $value;
        return $this;
    }

    /**
     * Convert to grayscale.
     */
    public function grayscale(bool $enabled = true): static
    {
        $this->params['grayscale'] = $enabled;
        return $this;
    }

    // =========================================================================
    // CANVAS / E-COMMERCE
    // =========================================================================

    /**
     * Set the background color.
     *
     * Supported: 'white', 'transparent', or hex like 'ffffff' / '#FFFFFF'
     *
     * @param string $color
     */
    public function background(string $color): static
    {
        $this->params['bg'] = $color;
        return $this;
    }

    /**
     * Set a square canvas size for e-commerce style images.
     *
     * @param int $size Canvas size in pixels (max 4096)
     */
    public function canvas(int $size): static
    {
        $this->params['canvas'] = $size;
        return $this;
    }

    /**
     * Set padding from canvas edge.
     *
     * @param int $padding Padding in pixels (max 1024)
     */
    public function pad(int $padding): static
    {
        $this->params['pad'] = $padding;
        return $this;
    }

    /**
     * Request AI background removal. Requires Pro tier or BG service enabled.
     */
    public function removeBg(bool $enabled = true): static
    {
        $this->params['remove_bg'] = $enabled;
        return $this;
    }

    /**
     * Mark this variant as an e-commerce operation.
     * This is a semantic flag — combine with canvas(), pad(), background() for full effect.
     */
    public function ecommerce(): static
    {
        $this->params['ecommerce'] = true;
        return $this;
    }

    /**
     * Shorthand for creating a standard e-commerce product image.
     *
     * Uses defaults from config('tinypeexi.defaults.ecommerce') merged with
     * any overrides you pass in.
     *
     * @param int|null $canvasSize  Square canvas size (default: 1024)
     * @param int|null $padding     Padding from edge (default: 40)
     * @param string|null $background Background color (default: 'white')
     * @param string|null $format   Output format (default: 'jpeg')
     * @param int|null $quality     Compression quality (default: 85)
     * @return VariantDto
     * @throws TinyPeexiException
     */
    public function ecommerceVariant(
        ?int $canvasSize = null,
        ?int $padding = null,
        ?string $background = null,
        ?string $format = null,
        ?int $quality = null,
    ): VariantDto {
        $defaults = $this->config['defaults']['ecommerce'] ?? [];

        $this->params['ecommerce'] = true;
        $this->params['canvas'] = $canvasSize ?? ($defaults['canvas'] ?? 1024);
        $this->params['pad'] = $padding ?? ($defaults['pad'] ?? 40);
        $this->params['bg'] = $background ?? ($defaults['background'] ?? 'white');
        $this->params['format'] = $format ?? ($defaults['format'] ?? 'jpeg');
        $this->params['q'] = $quality ?? ($defaults['quality'] ?? 85);

        return $this->generate();
    }

    // =========================================================================
    // WATERMARK
    // =========================================================================

    /**
     * Add an image watermark.
     *
     * @param string $assetSha SHA256 of the watermark image asset
     * @param string $position Position: 'center', 'topleft', 'topright', 'bottomleft', 'bottomright', 'tile'
     * @param float $opacity Opacity 0.0 - 1.0
     * @param float $scale Scale relative to image 0.01 - 1.0
     */
    public function watermark(
        string $assetSha,
        string $position = 'bottomright',
        float $opacity = 0.5,
        float $scale = 0.15,
    ): static {
        $this->params['watermark'] = $assetSha;
        $this->params['wm_pos'] = $position;
        $this->params['wm_opacity'] = $opacity;
        $this->params['wm_scale'] = $scale;
        return $this;
    }

    // =========================================================================
    // PRESETS
    // =========================================================================

    /**
     * Apply a named preset from config('tinypeexi.defaults').
     *
     * Example:
     *   TinyPeexi::variant($sha)->preset('thumbnail')->generate();
     *
     * @param string $name The preset name (e.g. 'thumbnail', 'ecommerce', 'watermarked')
     */
    public function preset(string $name): static
    {
        $presets = $this->config['defaults'] ?? [];

        if (!isset($presets[$name])) {
            throw new TinyPeexiException("Preset '{$name}' not found in tinypeexi config.");
        }

        $preset = $presets[$name];

        // Map preset keys to the API parameter names
        $keyMap = [
            'width' => 'w',
            'height' => 'h',
            'quality' => 'q',
            'background' => 'bg',
        ];

        foreach ($preset as $key => $value) {
            $paramKey = $keyMap[$key] ?? $key;
            $this->params[$paramKey] = $value;
        }

        return $this;
    }

    // =========================================================================
    // BATCH
    // =========================================================================

    /**
     * Generate multiple size variants in one call.
     *
     * @param array $sizes Array of widths, e.g. [400, 800, 1200]
     * @param string|null $format Output format for all sizes
     * @return void
     * @throws TinyPeexiException
     */
    public function batch(array $sizes, ?string $format = null): void
    {
        $payload = [
            'sha' => $this->sha,
            'sizes' => implode(',', $sizes),
        ];

        if ($format) {
            $payload['format'] = $format;
        }

        $response = app('tinypeexi')->client ?? null;

        // Use the client's internal HTTP to post to batch endpoint
        $this->client->requestBatchVariant($payload);
    }

    // =========================================================================
    // EXECUTE
    // =========================================================================

    /**
     * Generate the variant by sending the request to aipeexi.
     *
     * @return VariantDto
     * @throws TinyPeexiException
     */
    public function generate(): VariantDto
    {
        $payload = array_merge(['sha' => $this->sha], $this->params);

        $this->client->requestVariant($payload);

        // Build the delivery URL
        $deliveryUrl = $this->buildDeliveryUrl();
        $format = $this->params['format'] ?? 'webp';
        $width = $this->params['w'] ?? null;
        $name = $width ? "w{$width}.{$format}" : "original.{$format}";

        return new VariantDto(
            sha: $this->sha,
            name: $name,
            format: $format,
            deliveryUrl: $deliveryUrl,
        );
    }

    /**
     * Build the public delivery URL for the variant.
     */
    protected function buildDeliveryUrl(): string
    {
        $baseUrl = rtrim($this->config['delivery_url'] ?? $this->config['api_url'] ?? 'http://localhost:8080', '/');
        $tenant = $this->config['tenant_slug'] ?? 'dev';
        $format = $this->params['format'] ?? 'webp';
        $width = $this->params['w'] ?? null;
        $name = $width ? "w{$width}.{$format}" : "original.{$format}";

        return "{$baseUrl}/a/{$tenant}/{$this->sha}/{$name}";
    }

    /**
     * Get the current parameters array (useful for debugging).
     */
    public function toArray(): array
    {
        return array_merge(['sha' => $this->sha], $this->params);
    }
}
