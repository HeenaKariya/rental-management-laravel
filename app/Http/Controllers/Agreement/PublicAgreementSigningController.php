<?php

namespace App\Http\Controllers\Agreement;

use App\Http\Controllers\Controller;
use App\Models\PropertyActivityLog;
use App\Models\RentAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicAgreementSigningController extends Controller
{
    public function show(string $token): View
    {
        $agreement = RentAgreement::query()
            ->with(['lease.unit.property', 'tenant'])
            ->where('token', $token)
            ->firstOrFail();

        if ($agreement->status === 'generated') {
            $agreement->forceFill([
                'first_viewed_at' => $agreement->first_viewed_at ?: now(),
                'status' => 'viewed',
            ])->save();
        }

        return view('agreements.public-sign', [
            'agreement' => $agreement,
        ]);
    }

    public function sign(Request $request, string $token): RedirectResponse
    {
        $agreement = RentAgreement::query()->where('token', $token)->firstOrFail();

        if (! in_array($agreement->status, ['generated', 'viewed'], true)) {
            return back()->withErrors(['signature_label' => 'This agreement is no longer available for signing.']);
        }

        $data = $request->validate([
            'signature_label' => ['required', 'string', 'max:255'],
        ]);

        $signedAt = now();

        $agreement->loadMissing(['lease.unit.property', 'tenant']);
        $pdfBinary = Pdf::loadView('agreements.signed-pdf', [
            'agreement' => $agreement,
            'signatureLabel' => $data['signature_label'],
            'signedAt' => $signedAt,
        ])->setPaper('a4')->output();
        $pdfPath = sprintf('agreements/%d/%d-signed.pdf', $agreement->lease_id, $agreement->id);
        Storage::disk('public')->put($pdfPath, $pdfBinary);
        $pdfHash = hash('sha256', $pdfBinary);

        $agreement->forceFill([
            'signed_at' => $signedAt,
            'signature_label' => $data['signature_label'],
            'signed_pdf_path' => $pdfPath,
            'signed_pdf_hash' => $pdfHash,
            'signed_content_hash' => hash('sha256', $agreement->generated_content.'|'.$data['signature_label'].'|'.$signedAt->toIso8601String()),
            'signing_device' => substr((string) $request->userAgent(), 0, 255),
            'signing_ip' => (string) $request->ip(),
            'signing_method' => 'typed_name',
            'status' => 'signed',
        ])->save();

        $agreement->loadMissing('lease.unit.property');
        PropertyActivityLog::record($agreement->lease->unit->property, 'property.agreement_signed', null, [
            'agreement_id' => $agreement->id,
            'lease_id' => $agreement->lease_id,
            'signing_method' => 'typed_name',
        ]);

        return back()->with('status', 'Agreement signed successfully.');
    }
}