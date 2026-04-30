<?php

namespace App\Http\Controllers\Agreement;

use App\Http\Controllers\Controller;
use App\Models\AgreementTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgreementTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AgreementTemplate::class);

        $templates = AgreementTemplate::query()->latest()->get();

        return view('agreements.templates.index', [
            'statusOptions' => AgreementTemplate::STATUSES,
            'templates' => $templates,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AgreementTemplate::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(AgreementTemplate::STATUSES)],
        ]);

        AgreementTemplate::query()->create([
            ...$data,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return back()->with('status', 'Agreement template created.');
    }

    public function update(Request $request, AgreementTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(AgreementTemplate::STATUSES)],
        ]);

        $template->forceFill([
            ...$data,
            'updated_by' => $request->user()?->id,
        ])->save();

        return back()->with('status', 'Agreement template updated.');
    }

    public function destroy(AgreementTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);
        AgreementTemplate::query()->whereKey($template->id)->delete();

        return back()->with('status', 'Agreement template deleted.');
    }
}