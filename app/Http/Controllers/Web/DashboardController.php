<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $stats = null;
        $recentUsers = collect();

        if ($user->isAdministrator()) {
            $stats = [
                'users' => User::query()->count(),
                'medical_officers' => User::query()->where('user_type', User::ROLE_MEDICAL_OFFICER)->count(),
                'dermatologists' => User::query()->where('user_type', User::ROLE_DERMATOLOGIST)->count(),
            ];

            $recentUsers = User::query()->latest('created_at')->limit(8)->get();
        }

        return view('dashboard', compact('user', 'stats', 'recentUsers'));
    }
}
