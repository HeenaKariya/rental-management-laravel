<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivityLog;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyOwnerController extends Controller
{
    public function sync(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('assignManager', $property);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'owners' => ['required', 'array', 'min:1'],
            'owners.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'owners.*.owner_name' => ['nullable', 'string', 'max:255'],
            'owners.*.ownership_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'owners.*.capital_contribution' => ['nullable', 'numeric', 'min:0'],
            'owners.*.notes' => ['nullable', 'string'],
        ]);

        $owners = collect($data['owners'])
            ->filter(fn (array $owner) => filled($owner['user_id'] ?? null) || filled($owner['owner_name'] ?? null))
            ->values();

        if ($owners->isEmpty()) {
            return back()->withErrors(['owners' => 'Provide at least one owner row.'])->withInput();
        }

        if ($owners->contains(fn (array $owner) => blank($owner['user_id'] ?? null) && blank($owner['owner_name'] ?? null))) {
            return back()->withErrors(['owners' => 'Each owner row must use an existing user or an owner name.'])->withInput();
        }

        $totalOwnership = round((float) $owners->sum(fn (array $owner) => (float) $owner['ownership_pct']), 2);

        if ($totalOwnership !== 100.0) {
            return back()->withErrors(['owners' => 'Ownership total must equal exactly 100.00 percent.'])->withInput();
        }

        DB::transaction(function () use ($owners, $property, $user): void {
            $property->owners()->delete();

            foreach ($owners as $owner) {
                $property->owners()->create([
                    'user_id' => $owner['user_id'] ?: null,
                    'owner_name' => $owner['owner_name'] ?: null,
                    'ownership_pct' => $owner['ownership_pct'],
                    'capital_contribution' => $owner['capital_contribution'] ?? 0,
                    'notes' => $owner['notes'] ?? null,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            PropertyActivityLog::record($property, 'property.ownership_updated', $user, [
                'owners' => $owners->count(),
                'total_ownership_pct' => 100,
            ]);
        });

        return back()->with('status', 'Ownership splits saved.');
    }
}