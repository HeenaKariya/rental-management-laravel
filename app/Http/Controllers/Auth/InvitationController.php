<?php

namespace App\Http\Controllers\Auth;

use App\Models\Invitation;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function create(): View
    {
        return view('auth.invitations.create', [
            'roles' => Role::query()
                ->where('slug', '!=', 'super_admin')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::exists(Role::class, 'slug')],
        ]);

        $roleId = Role::query()->where('slug', $validated['role'])->value('id');

        $invitation = Invitation::issue([
            'email' => $validated['email'],
            'role_id' => $roleId,
            'invited_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('invitations.create')
            ->with('status', 'Invitation created for '.$invitation->email)
            ->with('invitation_url', route('invitations.accept', $invitation->token));
    }

    public function accept(string $token): RedirectResponse
    {
        return redirect()->route('register', ['invite' => $token]);
    }
}
