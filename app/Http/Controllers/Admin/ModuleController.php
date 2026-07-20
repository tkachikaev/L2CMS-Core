<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class ModuleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(ModuleManager $modules): View
    {
        $installedModules = $modules->installed();

        return view('admin.modules.index', [
            'modules' => $installedModules,
            'enabledCount' => collect($installedModules)->where('enabled', true)->count(),
            'problemCount' => collect($installedModules)
                ->whereIn('status', ['invalid', 'incompatible', 'missing', 'update_pending', 'runtime_error'])
                ->count(),
        ]);
    }

    public function enable(Request $request, string $module, ModuleManager $modules): RedirectResponse
    {
        $previousModule = $modules->inspect($module);

        try {
            $resolvedModule = $modules->enable($module);
        } catch (RuntimeException $exception) {
            $this->auditLogger->failed(
                category: 'module',
                action: 'module.enable_failed',
                target: $module,
                details: ['reason' => $exception->getMessage()],
            );

            return redirect()
                ->route('admin.modules.index')
                ->withErrors(['module' => $exception->getMessage()]);
        }

        Log::notice('KaevCMS module enabled.', [
            'admin_id' => Auth::guard('admin')->id(),
            'module_id' => $module,
            'module_version' => $resolvedModule['version'],
            'ip_address' => $request->ip(),
        ]);

        $this->auditLogger->success(
            category: 'module',
            action: $previousModule['update_available'] ? 'module.update_approved' : 'module.enabled',
            target: $module,
            details: [
                'module_id' => $module,
                'module_name' => $resolvedModule['name'],
                'module_version' => $resolvedModule['version'],
                'previous_module_version' => $previousModule['stored_version'],
            ],
        );

        return redirect()
            ->route('admin.modules.index')
            ->with('status', __('Module :module enabled. It will be active from the next request.', ['module' => $resolvedModule['name']]));
    }

    public function disable(Request $request, string $module, ModuleManager $modules): RedirectResponse
    {
        try {
            $resolvedModule = $modules->disable($module);
        } catch (RuntimeException $exception) {
            $this->auditLogger->failed(
                category: 'module',
                action: 'module.disable_failed',
                target: $module,
                details: ['reason' => $exception->getMessage()],
            );

            return redirect()
                ->route('admin.modules.index')
                ->withErrors(['module' => $exception->getMessage()]);
        }

        Log::notice('KaevCMS module disabled.', [
            'admin_id' => Auth::guard('admin')->id(),
            'module_id' => $module,
            'module_version' => $resolvedModule['version'],
            'ip_address' => $request->ip(),
        ]);

        $this->auditLogger->success(
            category: 'module',
            action: 'module.disabled',
            target: $module,
            details: [
                'module_id' => $module,
                'module_name' => $resolvedModule['name'],
                'module_version' => $resolvedModule['version'],
            ],
        );

        return redirect()
            ->route('admin.modules.index')
            ->with('status', __('Module :module disabled. It will stop loading from the next request.', ['module' => $resolvedModule['name']]));
    }
}
