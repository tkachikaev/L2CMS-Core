@extends('admin.layouts.panel')
@section('title', __('Reward queue transfers'))
@section('description', __('Read-only journal of rewards written from KaevCMS to GameServer queues.'))
@section('content')
<div class="admin-overview audit-summary">
    <div class="admin-overview-stat"><span>{{ __('Total operations') }}</span><strong>{{ $totalCount }}</strong></div>
    <p class="admin-overview-copy">{{ __('KaevCMS records whether data reached kaev_reward_queue. Actual item delivery is controlled by the GameServer administrator or an external consumer.') }}</p>
</div>

<form class="admin-filter-form" method="GET" action="{{ route('admin.rewards.index') }}">
    <label><span>{{ __('Server') }}</span><select name="server"><option value="0">{{ __('All servers') }}</option>@foreach($servers as $server)<option value="{{ $server->id }}" @selected($activeServerId === $server->id)>{{ $server->nameFor() }}</option>@endforeach</select></label>
    <label><span>{{ __('Status') }}</span><select name="status"><option value="">{{ __('All statuses') }}</option>@foreach([\App\Models\RewardDelivery::STATUS_PENDING, \App\Models\RewardDelivery::STATUS_QUEUED, \App\Models\RewardDelivery::STATUS_FAILED, \App\Models\RewardDelivery::STATUS_REVIEW] as $status)<option value="{{ $status }}" @selected($activeStatus === $status)>{{ \App\Models\RewardDelivery::statusLabelFor($status) }}</option>@endforeach</select></label>
    <button class="button button-secondary" type="submit">{{ __('Apply') }}</button>
</form>

@if($deliveries->isEmpty())
    <div class="admin-empty-state empty-state"><div class="empty-state-mark" aria-hidden="true">Q</div><h2>{{ __('No reward queue transfers yet') }}</h2><p>{{ __('Operations will appear here after players send web inventory rewards to a GameServer queue.') }}</p></div>
@else
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>{{ __('Date and time') }}</th><th>{{ __('Player') }}</th><th>{{ __('Server') }}</th><th>{{ __('Character') }}</th><th>{{ __('Rewards') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
        @foreach($deliveries as $delivery)<tr>
            <td><strong>{{ $delivery->requested_at?->format('d.m.Y') }}</strong><span>{{ $delivery->requested_at?->format('H:i:s') }}</span></td>
            <td><strong>{{ $delivery->user?->name ?? '—' }}</strong><span>{{ $delivery->user?->email ?? '—' }}</span></td>
            <td>{{ $delivery->gameServer->nameFor() }}</td>
            <td><strong>{{ $delivery->character_name }}</strong><span>{{ $delivery->account_login }}</span></td>
            <td>@foreach($delivery->items as $item)<span>{{ $item->displayName() }} × {{ number_format($item->amount, 0, '.', ' ') }}</span>@if(! $loop->last)<br>@endif @endforeach</td>
            <td><span @class(['status-badge','status-badge-success'=>$delivery->status===\App\Models\RewardDelivery::STATUS_QUEUED,'status-badge-danger'=>$delivery->status===\App\Models\RewardDelivery::STATUS_FAILED,'status-badge-warning'=>$delivery->status===\App\Models\RewardDelivery::STATUS_REVIEW])>{{ $delivery->statusLabel() }}</span>@if($delivery->failure_code)<code>{{ $delivery->failure_code }}</code>@endif</td>
        </tr>@endforeach
    </tbody></table></div>
    @if($deliveries->hasPages())
        <nav class="simple-pagination" aria-label="{{ __('Reward queue page navigation') }}">
            @if($deliveries->onFirstPage())<span class="button button-secondary disabled">← {{ __('Back') }}</span>@else<a wire:navigate class="button button-secondary" href="{{ $deliveries->previousPageUrl() }}">← {{ __('Back') }}</a>@endif
            <span>{{ __('Page :current of :last', ['current' => $deliveries->currentPage(), 'last' => $deliveries->lastPage()]) }}</span>
            @if($deliveries->hasMorePages())<a wire:navigate class="button button-secondary" href="{{ $deliveries->nextPageUrl() }}">{{ __('Next') }} →</a>@else<span class="button button-secondary disabled">{{ __('Next') }} →</span>@endif
        </nav>
    @endif
@endif
@endsection
