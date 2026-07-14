<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdministratorController extends Controller
{
    public function index(): View
    {
        $administrators = Admin::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.administrators.index', [
            'administrators' => $administrators,
            'totalCount' => Admin::query()->count(),
            'activeCount' => Admin::query()->where('is_active', true)->count(),
            'currentAdmin' => Auth::guard('admin')->user(),
        ]);
    }

    public function create(): View
    {
        return view('admin.administrators.create');
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validateProfile($request, passwordRequired: true);

        $administrator = Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'locale' => app()->getLocale(),
        ]);

        $auditLogger->success(
            category: 'admin',
            action: 'administrator.created',
            target: $administrator,
            details: [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'is_active' => true,
            ],
        );

        return redirect()
            ->route('admin.administrators.index')
            ->with('status', __('Administrator created.'));
    }

    public function edit(Admin $administrator): View
    {
        $currentAdmin = Auth::guard('admin')->user();
        $activeCount = Admin::query()->where('is_active', true)->count();

        return view('admin.administrators.edit', [
            'administrator' => $administrator,
            'currentAdmin' => $currentAdmin,
            'activeCount' => $activeCount,
            'isCurrentAdmin' => $currentAdmin?->is($administrator) ?? false,
        ]);
    }

    public function update(Request $request, Admin $administrator, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validateProfile($request, $administrator);
        $before = $administrator->only(['name', 'email']);

        $administrator->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! $administrator->isDirty()) {
            return redirect()
                ->route('admin.administrators.edit', $administrator)
                ->with('status', __('No changes.'));
        }

        $administrator->save();
        $changes = $this->changedValues($before, $administrator, ['name', 'email']);

        $auditLogger->success(
            category: 'admin',
            action: 'administrator.updated',
            target: $administrator,
            details: ['changes' => $changes],
        );

        return redirect()
            ->route('admin.administrators.edit', $administrator)
            ->with('status', __('Administrator details saved.'));
    }

    public function updatePassword(Request $request, Admin $administrator, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();
        $isCurrentAdmin = $currentAdmin->is($administrator);

        $validator = Validator::make(
            [
                'current_password' => (string) $request->input('current_password', ''),
                'password' => (string) $request->input('password', ''),
                'password_confirmation' => (string) $request->input('password_confirmation', ''),
            ],
            [
                'current_password' => $isCurrentAdmin
                    ? ['required', 'string', 'max:4096']
                    : ['nullable', 'string', 'max:4096'],
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(12)->letters()->mixedCase()->numbers(),
                    'max:4096',
                ],
            ],
            [],
            [
                'current_password' => __('Current password validation attribute'),
                'password' => __('New password validation attribute'),
                'password_confirmation' => __('new password confirmation'),
            ],
        );

        $validated = $validator->validate();

        if ($isCurrentAdmin && ! Hash::check($validated['current_password'], $currentAdmin->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        $administrator->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
            'session_version' => $administrator->session_version + 1,
        ])->save();

        if ($isCurrentAdmin) {
            $request->session()->regenerate();
            $request->session()->put('admin_session_version', $administrator->session_version);
        }

        $auditLogger->success(
            category: 'admin',
            action: 'administrator.password_changed',
            target: $administrator,
            details: ['password_changed' => true],
        );

        return redirect()
            ->route('admin.administrators.edit', $administrator)
            ->with('status', __('The administrator password was changed.'));
    }

    public function updateStatus(Request $request, Admin $administrator, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $newStatus = (bool) $validated['is_active'];

        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();

        if (! $newStatus && $currentAdmin->is($administrator)) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot disable your own account.'),
            ]);
        }

        if ($administrator->is_active === $newStatus) {
            return back()->with('status', __('The administrator status did not change.'));
        }

        DB::transaction(function () use ($administrator, $newStatus): void {
            /** @var Admin $lockedAdministrator */
            $lockedAdministrator = Admin::query()
                ->whereKey($administrator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $newStatus) {
                $activeAdministrators = Admin::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get(['id']);

                if ($activeAdministrators->count() <= 1) {
                    throw ValidationException::withMessages([
                        'is_active' => __('The last active administrator account cannot be disabled.'),
                    ]);
                }
            }

            $lockedAdministrator->forceFill([
                'is_active' => $newStatus,
                'session_version' => $newStatus
                    ? $lockedAdministrator->session_version
                    : $lockedAdministrator->session_version + 1,
                'remember_token' => $newStatus
                    ? $lockedAdministrator->remember_token
                    : Str::random(60),
            ])->save();
        });

        $administrator->refresh();

        $auditLogger->success(
            category: 'admin',
            action: $newStatus ? 'administrator.activated' : 'administrator.deactivated',
            target: $administrator,
            details: [
                'old' => ! $newStatus,
                'new' => $newStatus,
            ],
        );

        return back()->with(
            'status',
            $newStatus ? __('The administrator account was enabled.') : __('The administrator account was disabled.'),
        );
    }

    /**
     * @return array{name: string, email: string, password?: string}
     */
    private function validateProfile(Request $request, ?Admin $administrator = null, bool $passwordRequired = false): array
    {
        $data = [
            'name' => trim((string) $request->input('name', '')),
            'email' => Str::lower(trim((string) $request->input('email', ''))),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($administrator?->getKey()),
            ],
        ];

        if ($passwordRequired) {
            $rules['password'] = [
                'required',
                'string',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
                'max:4096',
            ];
        }

        return Validator::make(
            $data,
            $rules,
            [],
            [
                'name' => __('administrator name'),
                'email' => 'email',
                'password' => __('Password validation attribute'),
                'password_confirmation' => __('password confirmation'),
            ],
        )->validate();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<int, string>  $fields
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changedValues(array $before, Admin $administrator, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $administrator->getAttribute($field);

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
