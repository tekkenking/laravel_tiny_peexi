<?php

namespace Tekkenking\TinyPeexi\DTOs;

class VariantDto
{
    public string $sha;
    public string $name;
    public string $format;
    public string $deliveryUrl;

    public function __construct(string $sha, string $name, string $format, string $deliveryUrl)
    {
        $this->sha = $sha;
        $this->name = $name;
        $this->format = $format;
        $this->deliveryUrl = $deliveryUrl;
    }

    /**
     * Get the public delivery URL for this variant.
     */
    public function url(): string
    {
        return $this->deliveryUrl;
    }
}
