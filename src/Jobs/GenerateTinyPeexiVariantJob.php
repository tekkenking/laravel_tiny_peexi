<?php

namespace Tekkenking\TinyPeexi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tekkenking\TinyPeexi\Services\TinyPeexiClient;

class GenerateTinyPeexiVariantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $sha;
    public array $params;

    /**
     * Create a new job instance.
     *
     * @param string $sha
     * @param array $params E.g. ['w' => 800, 'h' => 900]
     */
    public function __construct(string $sha, array $params)
    {
        $this->sha = $sha;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(TinyPeexiClient $client): void
    {
        $builder = $client->variant($this->sha);

        foreach ($this->params as $key => $value) {
            $builder->setRawParam($key, $value);
        }

        $builder->generate();
    }
}
