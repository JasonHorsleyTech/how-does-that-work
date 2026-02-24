<?php

namespace App\Jobs;

use App\Models\Turn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GradeTurn implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Turn $turn) {}

    public function handle(): void
    {
        // Implemented in US-013
    }
}
