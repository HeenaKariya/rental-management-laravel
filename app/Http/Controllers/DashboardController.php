<?php

namespace App\Http\Controllers;

use App\Models\AuthAuditLog;
use App\Models\Invitation;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('tenant')) {
            return $this->tenantPortal($user);
        }

        $visibleProperties = $this->visiblePropertiesFor($user);
        $propertiesCount = $visibleProperties->count();
        $activePropertiesCount = $visibleProperties->where('lifecycle_stage', 'active')->count();
        $draftPropertiesCount = $visibleProperties->where('lifecycle_stage', 'draft')->count();
        $managerAssignmentsCount = $user->hasRole('super_admin')
            ? User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'manager'))->count()
            : $user->managedProperties()->count();
        $openInvitationsCount = 0;

        if ($user->hasRole('super_admin')) {
            $openInvitationsCount = Invitation::query()
                ->get()
                ->filter(fn (Invitation $invitation) => $invitation->accepted_at === null && $invitation->expires_at?->isFuture())
                ->count();
        }

        $recentAuthEventsCount = $user->hasRole('super_admin')
            ? AuthAuditLog::query()->where('occurred_at', '>=', now()->subDay())->count()
            : AuthAuditLog::query()->where('user_id', $user->id)->where('occurred_at', '>=', now()->subDay())->count();

        $summary = [
            'properties' => $propertiesCount,
            'activeProperties' => $activePropertiesCount,
            'draftProperties' => $draftPropertiesCount,
            'managerAssignments' => $managerAssignmentsCount,
            'openInvitations' => $openInvitationsCount,
            'recentAuthEvents' => $recentAuthEventsCount,
        ];

        $properties = $visibleProperties->take(5);

        $authEvents = AuthAuditLog::query()
            ->with('user.roles')
            ->when(! $user->hasRole('super_admin'), fn ($query) => $query->where('user_id', $user->id))
            ->latest('occurred_at')
            ->limit(6)
            ->get();

        $quickActions = collect([
            [
                'label' => 'Add property',
                'route' => route('properties.create'),
                'style' => 'solid',
                'visible' => $user->hasAnyRole(['super_admin', 'manager']),
            ],
            [
                'label' => 'View properties',
                'route' => route('properties.index'),
                'style' => 'ghost',
                'visible' => $user->hasAnyRole(['super_admin', 'manager']),
            ],
            [
                'label' => 'Security settings',
                'route' => route('settings.security'),
                'style' => 'violet',
                'visible' => true,
            ],
            [
                'label' => '2FA oversight',
                'route' => route('admin.security.two-factor.index'),
                'style' => 'ghost',
                'visible' => $user->hasRole('super_admin'),
            ],
            [
                'label' => 'Invite manager',
                'route' => route('invitations.create'),
                'style' => 'lime',
                'visible' => $user->hasRole('super_admin'),
            ],
        ])->where('visible');

        return view('dashboard', [
            'authEvents' => $authEvents,
            'properties' => $properties,
            'quickActions' => $quickActions,
            'summary' => $summary,
            'user' => $user,
        ]);
    }

    private function tenantPortal(User $user): View
    {
        $tenant = Tenant::query()
            ->visibleTo($user)
            ->with(['documents', 'unit.property'])
            ->first();

        $leases = Lease::query()
            ->visibleTo($user)
            ->with(['deposit', 'unit.property'])
            ->latest('start_on')
            ->get();

        $deposits = LeaseDeposit::query()
            ->visibleTo($user)
            ->with(['lease.unit.property'])
            ->latest()
            ->get();

        $authEvents = AuthAuditLog::query()
            ->with('user.roles')
            ->where('user_id', $user->id)
            ->latest('occurred_at')
            ->limit(6)
            ->get();

        $summary = [
            'documents' => $tenant?->documents->count() ?? 0,
            'depositBalance' => $deposits->sum(fn (LeaseDeposit $deposit) => (float) $deposit->current_balance),
            'leases' => $leases->count(),
            'activeLeases' => $leases->where('status', 'active')->count(),
        ];

        return view('dashboard-tenant', [
            'authEvents' => $authEvents,
            'deposits' => $deposits,
            'leases' => $leases,
            'summary' => $summary,
            'tenant' => $tenant,
            'user' => $user,
        ]);
    }

    private function visiblePropertiesFor(User $user): Collection
    {
        if ($user->hasRole('super_admin')) {
            return Property::query()
                ->with(['managers'])
                ->latest()
                ->get();
        }

        return $user->managedProperties()
            ->with(['managers'])
            ->orderByDesc('properties.created_at')
            ->get();
    }
}
