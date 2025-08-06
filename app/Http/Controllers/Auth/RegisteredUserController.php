<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'departments' => \App\Models\Department::select('id', 'name')->get(),
            'campuses' => \App\Models\Campus::select('id', 'name')->get(),
            'positions' => \App\Models\Position::select('id', 'title')->get(),
            'buildings' => \App\Models\Building::select('id', 'name')->get(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            'card_number' => ['nullable', 'string', 'max:255'],
            'telegram_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'building_id' => ['nullable', 'exists:buildings,id'],
            'default_department_id' => ['nullable', 'exists:departments,id'],
            'default_campus_id' => ['nullable', 'exists:campuses,id'],
            'current_position_id' => ['nullable', 'exists:positions,id'],

            'profile_url' => ['nullable', 'image', 'max:2048'],
            'signature_url' => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle file uploads
        $profileUrl = $request->hasFile('profile_url')
            ? $request->file('profile_url')->store('profiles', 'public')
            : null;

        $signatureUrl = $request->hasFile('signature_url')
            ? $request->file('signature_url')->store('signatures', 'public')
            : null;

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),

            'card_number' => $validated['card_number'] ?? null,
            'telegram_id' => $validated['telegram_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'building_id' => $validated['building_id'] ?? null,
            'default_department_id' => $validated['default_department_id'] ?? null,
            'default_campus_id' => $validated['default_campus_id'] ?? null,
            'current_position_id' => $validated['current_position_id'] ?? null,

            'profile_url' => $profileUrl,
            'signature_url' => $signatureUrl,
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
