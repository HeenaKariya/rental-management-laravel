<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivityLog;
use App\Models\PropertySale;
use App\Models\PropertySaleLead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertySaleController extends Controller
{
    public function show(Request $request, Property $property): View
    {
        $this->authorize('view', $property);

        $property->loadMissing([
            'purchase',
            'sale.closer',
            'sale.leads',
            'owners.user',
        ]);

        $sale = $property->sale;

        return view('finance.sale', [
            'ownerShares' => $sale?->ownerShares() ?? [],
            'property' => $property,
            'sale' => $sale,
            'leadStatusOptions' => PropertySaleLead::STATUSES,
            'user' => $request->user(),
        ]);
    }

    public function storeListing(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        if ($property->lifecycle_stage === 'sold') {
            return back()->withErrors(['listing_date' => 'This property has already been sold and is read-only.']);
        }

        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'listing_date' => ['required', 'date'],
            'asking_price' => ['required', 'numeric', 'min:0'],
            'broker_name' => ['nullable', 'string', 'max:255'],
            'broker_contact' => ['nullable', 'string', 'max:255'],
            'listing_notes' => ['nullable', 'string'],
        ]);

        $property->sale()->updateOrCreate(
            ['property_id' => $property->id],
            [
                ...$data,
                'status' => 'for_sale',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        PropertyActivityLog::record($property, 'property.sale_listed', $user, [
            'asking_price' => (float) $data['asking_price'],
            'listing_date' => $data['listing_date'],
        ]);

        return back()->with('status', 'Sale listing saved.');
    }

    public function storeLead(Request $request, Property $property, PropertySale $sale): RedirectResponse
    {
        $this->authorize('update', $property);
        abort_unless($sale->property_id === $property->id, 404);

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_contact' => ['nullable', 'string', 'max:255'],
            'inquiry_date' => ['required', 'date'],
            'offer_amount' => ['nullable', 'numeric', 'min:0'],
            'offer_date' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(PropertySaleLead::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $sale->leads()->create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        PropertyActivityLog::record($property, 'property.sale_lead_logged', $request->user(), [
            'buyer_name' => $data['buyer_name'],
            'status' => $data['status'],
        ]);

        return back()->with('status', 'Sale lead logged.');
    }

    public function closeSale(Request $request, Property $property, PropertySale $sale): RedirectResponse
    {
        $this->authorize('update', $property);
        abort_unless($sale->property_id === $property->id, 404);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'final_sale_price' => ['required', 'numeric', 'min:0'],
            'sale_date' => ['required', 'date'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_contact' => ['nullable', 'string', 'max:255'],
            'broker_commission' => ['nullable', 'numeric', 'min:0'],
            'closing_costs' => ['nullable', 'numeric', 'min:0'],
            'sale_deed' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'sale_notes' => ['nullable', 'string'],
        ]);

        $totalAcquisitionCost = (float) ($property->purchase?->total_acquisition_cost ?? 0);
        $netSaleProceeds = (float) $data['final_sale_price']
            - (float) ($data['broker_commission'] ?? 0)
            - (float) ($data['closing_costs'] ?? 0);
        $grossProfitLoss = $netSaleProceeds - $totalAcquisitionCost;
        $saleDeedPath = isset($data['sale_deed'])
            ? $data['sale_deed']->store('properties/'.$property->id.'/sales', 'public')
            : $sale->sale_deed_path;

        $sale->forceFill([
            'status' => 'closed',
            'final_sale_price' => $data['final_sale_price'],
            'sale_date' => $data['sale_date'],
            'buyer_name' => $data['buyer_name'],
            'buyer_contact' => $data['buyer_contact'] ?? null,
            'sale_deed_path' => $saleDeedPath,
            'broker_commission' => $data['broker_commission'] ?? 0,
            'closing_costs' => $data['closing_costs'] ?? 0,
            'sale_notes' => $data['sale_notes'] ?? null,
            'total_acquisition_cost_snapshot' => $totalAcquisitionCost,
            'net_sale_proceeds' => $netSaleProceeds,
            'gross_profit_loss' => $grossProfitLoss,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $property->forceFill([
            'lifecycle_stage' => 'sold',
            'updated_by' => $user->id,
        ])->save();

        PropertyActivityLog::record($property, 'property.sale_closed', $user, [
            'gross_profit_loss' => $grossProfitLoss,
            'net_sale_proceeds' => $netSaleProceeds,
            'sale_date' => $data['sale_date'],
        ]);

        return back()->with('status', 'Sale closed and property marked as sold.');
    }
}