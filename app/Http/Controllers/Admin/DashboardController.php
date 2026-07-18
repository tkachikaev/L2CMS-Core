<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mail\MailDeliveryMonitor;
use App\Services\MailSettings;
use App\Services\Servers\ServerMonitorCoordinator;
use App\Services\Servers\ServerStatusOverview;
use App\Services\Servers\ServerStatusPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        ServerStatusOverview $statuses,
        ServerMonitorCoordinator $monitorCoordinator,
        MailSettings $mailSettings,
        MailDeliveryMonitor $mailDeliveries,
    ): View {
        return view('admin.dashboard', [
            'admin' => Auth::guard('admin')->user(),
            'monitor' => $statuses->get(),
            'monitorRefreshDue' => $monitorCoordinator->isDue(),
            'mailSettings' => $mailSettings->values(),
            'mailDelivery' => $mailDeliveries->overview(),
        ]);
    }

    public function status(
        ServerMonitorCoordinator $monitorCoordinator,
        ServerStatusOverview $statuses,
        ServerStatusPayload $payload,
    ): JsonResponse {
        $refresh = $monitorCoordinator->refreshIfDue();

        return response()
            ->json([
                'refreshing' => $refresh['refreshing'],
                'fresh' => ! $monitorCoordinator->isDue(),
                'monitor' => $payload->forAdmin($statuses->get()),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function refresh(ServerMonitorCoordinator $monitorCoordinator): RedirectResponse
    {
        $result = $monitorCoordinator->refreshIfDue(force: true);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', $result['refreshing']
                ? __('Server status refresh is already running.')
                : __('Server status refreshed.'));
    }
}
