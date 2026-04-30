<?php

namespace App\Http\Controllers\Agreement;

use App\Http\Controllers\Controller;
use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\NotarizedAgreement;
use App\Models\PropertyActivityLog;
use App\Models\RentAgreement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaseAgreementController extends Controller
{
    public function show(Request $request, Lease $lease): View
    {
        $this->authorize('view', $lease);

        $lease->loadMissing([
            'tenant.user',
            'unit.property',
            'deposit',
            'agreements.template',
            'agreements.integrityChecker',
            'notarizedAgreements.uploader',
            'notarizedAgreements.reviewer',
        ]);

        return view('agreements.lease', [
            'activeTemplates' => AgreementTemplate::query()->where('status', 'active')->orderBy('name')->get(),
            'lease' => $lease,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('agreement_templates', 'id')],
            'manual_content' => ['nullable', 'string'],
        ]);

        $template = AgreementTemplate::query()->findOrFail((int) $data['template_id']);
        $resolvedContent = blank($data['manual_content'] ?? null)
            ? $this->resolveTemplate($template->body_html, $lease)
            : (string) $data['manual_content'];

        RentAgreement::query()
            ->where('lease_id', $lease->id)
            ->whereIn('status', ['generated', 'viewed'])
            ->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $user->id,
            ]);

        $agreement = RentAgreement::query()->create([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'template_id' => $template->id,
            'generated_content' => $resolvedContent,
            'token' => Str::random(64),
            'status' => 'generated',
            'generated_by' => $user->id,
        ]);

        PropertyActivityLog::record($lease->unit->property, 'property.agreement_generated', $user, [
            'agreement_id' => $agreement->id,
            'lease_id' => $lease->id,
            'status' => $agreement->status,
            'template' => $template->name,
        ]);

        return to_route('leases.agreement.show', $lease)
            ->with('status', 'Agreement generated. Share link: '.route('agreements.public.show', $agreement->token));
    }

    public function storeNotarized(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $document = $request->file('document');

        abort_unless($document !== null, 422);

        $notarizedAgreement = NotarizedAgreement::query()->create([
            'lease_id' => $lease->id,
            'disk' => 'public',
            'path' => $document->store('agreements/notarized/'.$lease->id, 'public'),
            'original_name' => $document->getClientOriginalName(),
            'mime_type' => $document->getMimeType(),
            'file_size' => $document->getSize(),
            'status' => 'uploaded',
            'uploaded_at' => now(),
            'uploaded_by' => $user->id,
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        PropertyActivityLog::record($lease->unit->property, 'property.notarized_agreement_uploaded', $user, [
            'notarized_agreement_id' => $notarizedAgreement->id,
            'lease_id' => $lease->id,
            'status' => $notarizedAgreement->status,
        ]);

        return to_route('leases.agreement.show', $lease)->with('status', 'Notarized agreement uploaded.');
    }

    public function updateNotarizedStatus(Request $request, Lease $lease, NotarizedAgreement $notarizedAgreement): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($notarizedAgreement->lease_id === $lease->id, 404);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(NotarizedAgreement::STATUSES)],
            'review_notes' => ['nullable', 'string'],
        ]);

        $notarizedAgreement->forceFill([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
        ])->save();

        PropertyActivityLog::record($lease->unit->property, 'property.notarized_agreement_status_updated', $user, [
            'notarized_agreement_id' => $notarizedAgreement->id,
            'lease_id' => $lease->id,
            'status' => $notarizedAgreement->status,
        ]);

        return to_route('leases.agreement.show', $lease)->with('status', 'Notarized agreement status updated.');
    }

    public function downloadNotarized(Request $request, Lease $lease, NotarizedAgreement $notarizedAgreement): BinaryFileResponse
    {
        $this->authorize('view', $lease);
        abort_unless($notarizedAgreement->lease_id === $lease->id, 404);
        abort_unless(Storage::disk($notarizedAgreement->disk)->exists($notarizedAgreement->path), 404);

        $fileName = $notarizedAgreement->original_name ?: sprintf('notarized-agreement-%s.%s', $lease->lease_number, 'pdf');

        return response()->download(
            Storage::disk($notarizedAgreement->disk)->path($notarizedAgreement->path),
            $fileName
        );
    }

    public function verifyIntegrity(Request $request, Lease $lease, RentAgreement $agreement): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($agreement->lease_id === $lease->id, 404);

        if (! $agreement->signed_pdf_path || ! $agreement->signed_pdf_hash) {
            return back()->withErrors(['integrity' => 'Signed PDF is not available for integrity verification.']);
        }

        if (! Storage::disk('public')->exists($agreement->signed_pdf_path)) {
            $agreement->forceFill([
                'integrity_last_checked_at' => now(),
                'integrity_check_notes' => 'Signed PDF file is missing on disk.',
                'integrity_check_status' => 'tampered',
                'integrity_checked_by' => $request->user()?->id,
            ])->save();

            PropertyActivityLog::record($lease->unit->property, 'property.agreement_integrity_failed', $request->user(), [
                'agreement_id' => $agreement->id,
                'lease_id' => $lease->id,
                'reason' => 'missing_file',
            ]);

            return back()->withErrors(['integrity' => 'Signed PDF file is missing; integrity failed.']);
        }

        $currentHash = hash('sha256', (string) Storage::disk('public')->get($agreement->signed_pdf_path));
        $status = hash_equals((string) $agreement->signed_pdf_hash, $currentHash) ? 'verified' : 'tampered';

        $agreement->forceFill([
            'integrity_last_checked_at' => now(),
            'integrity_check_notes' => $status === 'verified'
                ? 'Signed PDF hash matches stored fingerprint.'
                : 'Signed PDF hash mismatch detected.',
            'integrity_check_status' => $status,
            'integrity_checked_by' => $request->user()?->id,
        ])->save();

        PropertyActivityLog::record(
            $lease->unit->property,
            $status === 'verified' ? 'property.agreement_integrity_verified' : 'property.agreement_integrity_failed',
            $request->user(),
            [
                'agreement_id' => $agreement->id,
                'lease_id' => $lease->id,
                'status' => $status,
            ]
        );

        return back()->with('status', $status === 'verified'
            ? 'Integrity verification passed.'
            : 'Integrity verification failed. Potential tampering detected.');
    }

    public function downloadSignedPdf(Request $request, Lease $lease, RentAgreement $agreement): BinaryFileResponse
    {
        $this->authorize('view', $lease);
        abort_unless($agreement->lease_id === $lease->id, 404);
        abort_unless($agreement->signed_pdf_path && Storage::disk('public')->exists($agreement->signed_pdf_path), 404);

        return response()->download(
            Storage::disk('public')->path($agreement->signed_pdf_path),
            sprintf('lease-agreement-%s.pdf', $lease->lease_number)
        );
    }

    private function resolveTemplate(string $bodyHtml, Lease $lease): string
    {
        $lease->loadMissing(['tenant', 'unit.property', 'deposit']);

        $replacements = [
            '{{tenant_name}}' => $lease->tenant->full_name,
            '{{tenant_address}}' => (string) ($lease->tenant->address ?? 'N/A'),
            '{{property_name}}' => $lease->unit->property->title,
            '{{unit_number}}' => $lease->unit->unit_number,
            '{{lease_start_date}}' => $lease->start_on->toDateString(),
            '{{lease_end_date}}' => $lease->end_on->toDateString(),
            '{{monthly_rent}}' => number_format((float) $lease->rent_amount, 2, '.', ''),
            '{{security_deposit}}' => number_format((float) ($lease->deposit?->opening_amount ?? 0), 2, '.', ''),
            '{{landlord_name}}' => 'PropMgr Landlord',
            '{{agreement_date}}' => now()->toDateString(),
        ];

        return strtr($bodyHtml, $replacements);
    }
}