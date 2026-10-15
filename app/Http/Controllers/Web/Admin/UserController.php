<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class UserController extends Controller
{
    private const PAGE_SIZES = [5, 10, 15, 20, 25];

    public function index(Request $request): View
    {
        $query = User::query()->orderBy('last_name')->orderBy('first_name')->orderBy('id');
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $requestedRole = (string) $request->query('user_type', '');
        $userType = in_array($requestedRole, User::ROLES, true) ? $requestedRole : '';
        $requestedPageSize = (int) $request->query('per_page', 10);
        $perPage = in_array($requestedPageSize, self::PAGE_SIZES, true) ? $requestedPageSize : 10;

        if ($search !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $query->where(function ($query) use ($search, $terms): void {
                $query->where('email', 'like', '%'.$search.'%')
                    ->orWhere(function ($nameQuery) use ($terms): void {
                        foreach ($terms as $term) {
                            $nameQuery->where(function ($termQuery) use ($term): void {
                                $termQuery->where('first_name', 'like', '%'.$term.'%')
                                    ->orWhere('last_name', 'like', '%'.$term.'%');
                            });
                        }
                    });
            });
        }

        if ($userType === User::ROLE_ADMIN) {
            $query->where(fn ($query) => $query
                ->where('user_type', User::ROLE_ADMIN)
                ->orWhere('is_admin', true));
        } elseif ($userType !== '') {
            $query->where('user_type', $userType)->where('is_admin', false);
        }

        return view('admin.users.index', [
            'users' => $query->paginate($perPage)->withQueryString(),
            'filters' => [
                'q' => $search,
                'user_type' => $userType,
                'per_page' => $perPage,
            ],
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'user_type' => ['required', Rule::in(User::ROLES)],
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $willBeAdmin = $data['user_type'] === User::ROLE_ADMIN;
        if ($user->is($request->user()) && ! $willBeAdmin) {
            return back()->withErrors(['user_type' => 'You cannot remove your own administrator access.'])->withInput();
        }

        if ($user->isAdministrator() && ! $willBeAdmin && $this->activeAdministratorCount() <= 1) {
            return back()->withErrors(['user_type' => 'The last active administrator cannot be demoted.'])->withInput();
        }

        $user->fill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => mb_strtolower(trim($data['email'])),
            'user_type' => $data['user_type'],
            'is_admin' => $willBeAdmin,
            'phone' => $data['phone'] ?? null,
            'facility_name' => $data['facility_name'] ?? null,
            'specialty' => $data['specialty'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->delete();
        }

        $user->save();

        return redirect()->route('users.index')
            ->with('status', 'User details updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['users' => 'You cannot deactivate your own account.']);
        }

        if ($user->isAdministrator() && $this->activeAdministratorCount() <= 1) {
            return back()->withErrors(['users' => 'The last active administrator cannot be deactivated.']);
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('users.index')
            ->with('status', 'User deactivated. Their account can no longer sign in.');
    }

    private function activeAdministratorCount(): int
    {
        return User::query()->where(function ($query): void {
            $query->where('user_type', User::ROLE_ADMIN)->orWhere('is_admin', true);
        })->count();
    }
}
