<?php

namespace App\Jobs;

use App\Services\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $payload,
    ) {
        $this->onQueue(config('activity-log.queue', 'audit'));
    }

    public function handle(ActivityLogger $activityLogger): void
    {
        $activityLogger->writeNow($this->payload);
    }
}
