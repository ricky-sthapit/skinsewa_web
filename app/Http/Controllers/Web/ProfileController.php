<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ]);

        $request->user()->fill($data)->save();

        return redirect()->route('profile.show')->with('status', 'Your profile details were updated.');
    }
}
