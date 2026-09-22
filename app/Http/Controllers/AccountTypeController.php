<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AccountTypeController extends Controller
{
    protected const ROLE_SUPER_ADMIN = 'SUPER ADMINISTRATOR';

    /**
     * The six roles every role-check in this codebase (VehicleController,
     * MaintenanceController, UserController, etc.) hardcodes as PHP
     * constants. Renaming or deleting one of these here would silently break
     * access control everywhere else, since nothing in those controllers
     * reads back from this table at request time — they compare against the
     * literal string. A Super Administrator can still add further custom
     * types beyond these six (e.g. for a label used only in reports), but
     * a brand-new custom type has no special page access anywhere in the
     * system until a developer adds code for it — flagged in the UI so this
     * isn't a silent surprise.
     */
    protected const BUILT_IN_TYPES = [
        'SUPER ADMINISTRATOR',
        'ADMINISTRATOR',
        'UNIT ADMINISTRATOR',
        'STATION ADMINISTRATOR',
        'VIEWER',
        'DRIVER',
    ];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Per the user's own spec: this page is exclusive to the Super
     * Administrator — not even Administrator may manage account types.
     */
    protected function authorizeAccess(): void
    {
        if ($this->role(auth()->user()) !== self::ROLE_SUPER_ADMIN) {
            abort(403, 'Only the Super Administrator can manage account types.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        if ($request->ajax()) {
            $query = AccountType::orderBy('level');

            if ($search = trim((string) $request->input('search.value'))) {
                $query->where('type', 'like', "%{$search}%");
            }

            $totalRecords = AccountType::count();
            $filteredRecords = $query->count();

            $start = $request->input('start', 0);
            $length = $request->input('length', 10);

            $accountTypes = $query->skip($start)->take($length)->get();
            $usageCounts = User::query()
                ->selectRaw('account_type, count(*) as total')
                ->groupBy('account_type')
                ->pluck('total', 'account_type');

            $data = [];
            foreach ($accountTypes as $accountType) {
                $isBuiltIn = in_array(strtoupper($accountType->type), self::BUILT_IN_TYPES, true);
                $inUse = (int) ($usageCounts[$accountType->type] ?? 0);

                $nameHtml = '<div class="type-name">' . e($accountType->type) . '</div>' .
                            ($isBuiltIn
                                ? '<div class="type-desc"><i class="fas fa-lock mr-1"></i>Built-in system role</div>'
                                : '<div class="type-desc">Custom label — not wired to any page access yet</div>');

                $levelHtml = '<span class="badge badge-light px-2 py-1 border font-weight-bold" style="font-size:12px; border-radius:6px;">' .
                             'Level ' . (int) $accountType->level .
                             '</span>';

                $usageHtml = '<span class="badge badge-light px-2 py-1 border font-weight-bold" style="font-size:12px; border-radius:6px;">' .
                             '<i class="fas fa-users mr-1 text-primary"></i> ' . $inUse . ' account' . ($inUse === 1 ? '' : 's') .
                             '</span>';

                $actionsHtml = '<div class="text-right">' .
                               '<button class="action-btn mr-1 edit-account-type-btn" ' .
                               'data-id="' . $accountType->id . '" ' .
                               'data-type="' . e($accountType->type) . '" ' .
                               'data-level="' . (int) $accountType->level . '" ' .
                               'data-builtin="' . ($isBuiltIn ? '1' : '0') . '"><i class="fas fa-pen"></i></button>';

                if (! $isBuiltIn) {
                    $actionsHtml .= '<button class="action-btn btn-delete delete-account-type-btn" ' .
                                    'data-id="' . $accountType->id . '" ' .
                                    'data-type="' . e($accountType->type) . '" ' .
                                    'data-count="' . $inUse . '"><i class="fas fa-trash"></i></button>';
                }
                $actionsHtml .= '</div>';

                $data[] = [
                    'name_html'    => $nameHtml,
                    'level_html'   => $levelHtml,
                    'usage_html'   => $usageHtml,
                    'actions_html' => $actionsHtml,
                ];
            }

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
            ]);
        }

        $stats = [
            'total_types'  => AccountType::count(),
            'built_in'     => AccountType::whereIn('type', self::BUILT_IN_TYPES)->count(),
            'custom_types' => AccountType::whereNotIn('type', self::BUILT_IN_TYPES)->count(),
            'total_users'  => User::count(),
        ];

        $accountTypes = AccountType::orderBy('level')->get();

        return view('account_types.index', compact('stats', 'accountTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'type'  => ['required', 'string', 'max:100', 'unique:account_types,type'],
            'level' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $validated['type'] = strtoupper(trim($validated['type']));

        $accountType = AccountType::create($validated);

        ActivityLog::record(
            'created', 'AccountType', 'Added account type [' . $accountType->type . '].', $accountType, ['after' => $validated]
        );

        return redirect()->route('account-types.index')->with('success', 'Account type added successfully.');
    }

    public function update(Request $request, AccountType $accountType): RedirectResponse
    {
        $this->authorizeAccess();

        $isBuiltIn = in_array(strtoupper($accountType->type), self::BUILT_IN_TYPES, true);

        $validated = $request->validate([
            'type'  => [
                'required', 'string', 'max:100',
                Rule::unique('account_types', 'type')->ignore($accountType->id),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $validated['type'] = strtoupper(trim($validated['type']));

        // Built-in roles are hardcoded by their exact text throughout the app
        // (VehicleController, MaintenanceController, UserController, etc.) —
        // renaming one here would silently strip that role of all its access
        // everywhere else without a single error message. Level (display
        // order only) is always safe to change.
        if ($isBuiltIn && $validated['type'] !== strtoupper($accountType->type)) {
            return redirect()->route('account-types.index')
                ->withErrors(['error' => '"' . $accountType->type . '" is a built-in system role and cannot be renamed — only its sort level can be changed.']);
        }

        $before = $accountType->getOriginal();
        $accountType->update($validated);
        $changed = $accountType->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated', 'AccountType', 'Updated account type [' . $accountType->type . '].', $accountType,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('account-types.index')->with('success', 'Account type updated successfully.');
    }

    public function destroy(AccountType $accountType): RedirectResponse
    {
        $this->authorizeAccess();

        if (in_array(strtoupper($accountType->type), self::BUILT_IN_TYPES, true)) {
            return redirect()->route('account-types.index')
                ->withErrors(['error' => '"' . $accountType->type . '" is a built-in system role and cannot be deleted.']);
        }

        if (User::where('account_type', $accountType->type)->exists()) {
            return redirect()->route('account-types.index')
                ->withErrors(['error' => 'Cannot delete "' . $accountType->type . '" because it is still assigned to one or more user accounts.']);
        }

        $snapshot = $accountType->toArray();
        $type = $accountType->type;
        $accountType->delete();

        ActivityLog::record('deleted', 'AccountType', 'Removed account type [' . $type . '].', $accountType, ['before' => $snapshot]);

        return redirect()->route('account-types.index')->with('success', 'Account type removed successfully.');
    }
}
