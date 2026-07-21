<?php

namespace App\Jobs;

use App\Services\Rewards\RewardDeliveryProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ConfirmRewardDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $timeout = 30;

    public int $backoff = 10;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onConnection('database');
        $this->onQueue('rewards');
    }

    public function handle(RewardDeliveryProcessor $processor): void
    {
        if (! $processor->confirm($this->deliveryId)) {
            $this->release(10);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(RewardDeliveryProcessor::class)->markForReview(
            $this->deliveryId,
            'reward_bridge_confirmation_failed',
        );
    }
}
