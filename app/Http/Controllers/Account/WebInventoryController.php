<?php

namespace App\Http\Controllers\Account;

use App\Contracts\GameRewardDeliveryGateway;
use App\Http\Controllers\Controller;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Services\Rewards\RewardCharacterDirectory;
use App\Support\Rewards\RewardDeliveryCapabilities;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebInventoryController extends Controller
{
    public function __invoke(
        Request $request,
        GameRewardDeliveryGateway $deliveryGateway,
        RewardCharacterDirectory $characters,
    ): View {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $serverIds = RewardInventoryItem::query()
            ->where('user_id', $user->id)
            ->pluck('game_server_id')
            ->merge(RewardDelivery::query()->where('user_id', $user->id)->pluck('game_server_id'))
            ->unique()
            ->values();

        /** @var Collection<int,GameServer> $servers */
        $servers = GameServer::query()
            ->with('translations')
            ->whereIn('id', $serverIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $requestedServerId = max(0, (int) $request->query('server'));
        $selectedServer = $servers->firstWhere('id', $requestedServerId) ?? $servers->first();
        $activeView = $request->query('view') === 'history' ? 'history' : 'available';

        $availableItems = collect();
        $deliveries = RewardDelivery::query()->whereRaw('1 = 0')->paginate(20);
        $characterRows = [];
        $capabilities = RewardDeliveryCapabilities::unsupported();

        if ($selectedServer instanceof GameServer) {
            $availableItems = RewardInventoryItem::query()
                ->with('grant')
                ->where('user_id', $user->id)
                ->where('game_server_id', $selectedServer->id)
                ->where('status', RewardInventoryItem::STATUS_AVAILABLE)
                ->latest('id')
                ->get();

            $deliveries = RewardDelivery::query()
                ->with(['items', 'gameServer.translations'])
                ->where('user_id', $user->id)
                ->where('game_server_id', $selectedServer->id)
                ->latest('id')
                ->paginate(20)
                ->withQueryString();

            $capabilities = $deliveryGateway->capabilities($selectedServer);
            if ($capabilities->supported) {
                $characterRows = $characters->forServer($user, $selectedServer);
            }
        }

        return view('account-theme::web-inventory.index', [
            'user' => $user,
            'servers' => $servers,
            'selectedServer' => $selectedServer,
            'activeView' => $activeView,
            'availableItems' => $availableItems,
            'deliveries' => $deliveries,
            'characters' => $characterRows,
            'capabilities' => $capabilities,
            'deliveryUnavailableMessage' => $this->deliveryUnavailableMessage($capabilities),
            'requestToken' => (string) Str::uuid(),
        ]);
    }

    private function deliveryUnavailableMessage(RewardDeliveryCapabilities $capabilities): string
    {
        return match ($capabilities->reasonCode) {
            'reward_bridge_not_installed' => __('Kaev Reward Bridge is not installed for this GameServer. Rewards remain safe in the web inventory.'),
            'reward_bridge_protocol_mismatch' => __('The installed Kaev Reward Bridge version is incompatible with this KaevCMS release.'),
            'reward_bridge_offline' => __('Kaev Reward Bridge is not responding. Start GameServer and check the bridge installation.'),
            default => __('Rewards are safe in the web inventory, but automatic transfer is not supported by this GameServer driver yet.'),
        };
    }
}
