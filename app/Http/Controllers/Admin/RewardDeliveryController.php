<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $status = strtolower(trim((string) $request->query('status')));
        if (! in_array($status, [
            RewardDelivery::STATUS_PENDING,
            RewardDelivery::STATUS_QUEUED,
            RewardDelivery::STATUS_FAILED,
            RewardDelivery::STATUS_REVIEW,
        ], true)) {
            $status = null;
        }

        $serverId = max(0, (int) $request->query('server'));
        $query = RewardDelivery::query()
            ->with(['user', 'gameServer.translations', 'items'])
            ->latest('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($serverId > 0) {
            $query->where('game_server_id', $serverId);
        }

        return view('admin.rewards.index', [
            'deliveries' => $query->paginate(50)->withQueryString(),
            'activeStatus' => $status,
            'activeServerId' => $serverId,
            'servers' => GameServer::query()->with('translations')->orderBy('sort_order')->orderBy('id')->get(),
            'counts' => RewardDelivery::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'totalCount' => RewardDelivery::query()->count(),
        ]);
    }
}
