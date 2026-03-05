<?php

use Tekkenking\TinyPeexi\Facades\TinyPeexi;
use Tekkenking\TinyPeexi\Services\VariantBuilder;

if (!function_exists('tinypeexi')) {
    /**
     * Generate a TinyPeexi variant quickly inline, perfect for Blade templates.
     *
     * @param string $sha The SHA hash of the asset
     * @param string|array|null $options A preset name (string) or an array of parameters
     * @return VariantBuilder
     */
    function tinypeexi(string $sha, string|array|null $options = null): VariantBuilder
    {
        $builder = TinyPeexi::variant($sha);

        if (is_string($options)) {
            // Apply a preset
            $builder->preset($options);
        } elseif (is_array($options)) {
            // Apply manual parameters
            foreach ($options as $key => $value) {
                // Map common full-names backward to API keys for developer convenience
                $map = [
                    'width' => 'w',
                    'height' => 'h',
                    'quality' => 'q',
                    'background' => 'bg',
                ];

                $paramKey = $map[$key] ?? $key;

                // Expose internal setter logic dynamically
                $builder->setRawParam($paramKey, $value);
            }
        }

        return $builder;
    }
}
