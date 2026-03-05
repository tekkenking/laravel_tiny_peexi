<?php

namespace Tekkenking\TinyPeexi\DTOs;

class AssetDto
{
    public string $sha;

    public function __construct(string $sha)
    {
        $this->sha = $sha;
    }
}
