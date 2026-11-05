<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $bulk = route('admin.users.bulk');

        return view('admin.table', [
            'title' => __('Users'),
            'actions' => [['label' => __('New user'), 'sheet' => route('admin.users.create')]],
            'table' => DataTable::make([
                'id' => 'users',
                'source' => User::query()->with('role'),
                'columns' => [
                    ['key' => 'name', 'label' => __('Name'), 'sort' => true],
                    ['key' => 'email', 'label' => __('Email'), 'sort' => true],
                    ['key' => 'role', 'label' => __('Role'), 'render' => fn ($u) => $u->role?->name ?? '—'],
                    ['key' => 'is_active', 'label' => __('Status'), 'sort' => true, 'render' => fn ($u) => new HtmlString($u->is_active ? '<span class="badge badge-success">'.e(__('Active')).'</span>' : '<span class="badge badge-muted">'.e(__('Inactive')).'</span>')],
                    ['key' => 'totp_confirmed_at', 'label' => 'TOTP', 'sort' => true, 'render' => fn ($u) => new HtmlString($u->hasTotp() ? '<span class="badge badge-success">'.e(__('On')).'</span>' : '<span class="badge badge-muted">'.e(__('Off')).'</span>')],
                    ['key' => 'last_login_at', 'label' => __('Last login'), 'sort' => true, 'render' => fn ($u) => cms_date($u->last_login_at) ?: '—'],
                ],
                'search' => ['name', 'email'],
                'filters' => [
                    'role' => ['label' => __('Role'), 'column' => 'role_id', 'options' => Role::orderBy('name')->pluck('name', 'id')->all()],
                    'active' => ['label' => __('Status'), 'column' => 'is_active', 'options' => ['1' => __('Active'), '0' => __('Inactive')]],
                ],
                'default_sort' => ['name', 'asc'],
                'bulk_url' => $bulk,
                'bulk' => [
                    'activate' => ['label' => __('Activate')],
                    'deactivate' => ['label' => __('Deactivate')],
                    'delete' => ['label' => __('Delete'), 'danger' => true],
                ],
                'row_actions' => fn ($u) => [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route('admin.users.edit', $u->id)],
                    $u->hasTotp() && $u->id !== auth()->id() ? ['label' => __('Reset TOTP'), 'icon' => 'shield', 'post' => $bulk, 'fields' => ['ids[]' => [$u->id], 'action' => 'reset_totp'], 'confirm' => __('Turn off two-step verification for ":title"? Use this when they lost their phone and recovery codes.', ['title' => $u->name])] : null,
                    $u->id !== auth()->id() ? ['label' => __('Delete'), 'icon' => 'trash', 'post' => $bulk, 'fields' => ['ids[]' => [$u->id], 'action' => 'delete'], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $u->name])] : null,
                ],
            ]),
        ]);
    }

    public function form(?int $id = null)
    {
        $user = $id ? User::findOrFail($id) : new User(['is_active' => true]);

        return view('admin.partials.sheet-form', [
            'title' => $id ? __('Edit: :title', ['title' => $user->name]) : __('New user'),
            'action' => $id ? route('admin.users.update', $id) : route('admin.users.store'),
            'values' => ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => $user->is_active ? '1' : '0'],
            'fields' => [
                ['name' => 'name', 'label' => __('Name'), 'required' => true],
                ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => true],
                ['name' => 'role_id', 'label' => __('Role'), 'type' => 'select', 'options' => $this->assignableRoles()->pluck('name', 'id')->all()],
                ['name' => 'password', 'label' => __('Password'), 'type' => 'password', 'required' => ! $id, 'help' => $id ? __('Leave empty to keep the current password.') : __('At least 8 characters.')],
                ['name' => 'is_active', 'label' => __('Active'), 'type' => 'switch'],
            ],
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $user = $id ? User::findOrFail($id) : new User;
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'role_id' => ['required', Rule::in($this->assignableRoles()->pluck('id')->all())],
            'password' => [$id ? 'nullable' : 'required', Password::min(8)],
            'is_active' => 'nullable|in:0,1',
        ]);

        // Keeps at least one working administrator: the current admin cannot demote or disable themself.
        if ($id === auth()->id() && ((int) $data['role_id'] !== $user->role_id || ! ($data['is_active'] ?? false))) {
            return back()->with('error', __('You cannot change your own role or status.'));
        }

        // Only an administrator may change an administrator's account.
        if ($id && $user->hasCapability('*') && ! auth()->user()->hasCapability('*')) {
            abort(403);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        activity('user.'.($id ? 'updated' : 'created'), $user->email);

        return back()->with('success', __('Saved.'));
    }

    public function bulk(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:activate,deactivate,delete,reset_totp'])['action'];
        $isAdmin = auth()->user()->hasCapability('*');
        $count = 0;
        foreach (User::with('role')->whereIn('id', $this->ids($request))->get() as $user) {
            if ($user->id === auth()->id() || ($user->hasCapability('*') && ! $isAdmin)) {
                continue;
            }
            match ($action) {
                'activate' => $user->update(['is_active' => true]),
                'deactivate' => $user->update(['is_active' => false]),
                'delete' => $user->delete(),
                'reset_totp' => $user->disableTotp(),
            };
            activity("user.$action", $user->email);
            $count++;
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }

    /** A user cannot hand out more power than they have. */
    private function assignableRoles()
    {
        $me = auth()->user();

        return Role::orderBy('name')->get()->filter(function ($role) use ($me) {
            return $me->hasCapability('*') || collect($role->capabilities)->every(fn ($c) => $c !== '*' && $me->hasCapability($c));
        });
    }
}
