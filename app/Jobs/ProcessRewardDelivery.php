<?php

namespace App\Jobs;

use App\Services\Rewards\RewardDeliveryProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessRewardDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onConnection('database');
        $this->onQueue('rewards');
    }

    public function handle(RewardDeliveryProcessor $processor): void
    {
        $processor->process($this->deliveryId);
    }

    public function failed(Throwable $exception): void
    {
        app(RewardDeliveryProcessor::class)->markForReview(
            $this->deliveryId,
            'queue_job_failed',
        );
    }
}
