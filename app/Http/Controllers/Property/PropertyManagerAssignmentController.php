<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyManagerAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PropertyManagerAssignmentController extends Controller
{
    public function store(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('assignManager', $property);

        $data = $request->validate([
            'manager_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $manager = User::query()->with('roles')->findOrFail($data['manager_id']);

        if (! $manager->hasRole('manager')) {
            return back()->withErrors(['manager_id' => 'Only users with the Manager role can be assigned.']);
        }

        $property->assignManager($manager, $request->user());

        return back()->with('status', 'Manager assigned.');
    }

    public function destroy(Request $request, Property $property, PropertyManagerAssignment $assignment): RedirectResponse
    {
        $this->authorize('assignManager', $property);

        abort_unless($assignment->property_id === $property->id, 404);

        $property->revokeManagerAssignment($assignment, $request->user());

        return back()->with('status', 'Manager assignment revoked.');
    }
}
