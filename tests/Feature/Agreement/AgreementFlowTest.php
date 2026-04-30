<?php

namespace Tests\Feature\Agreement;

use App\Domain\Agreement\Notifications\SignedAgreementCopyNotification;
use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Property;
use App\Models\RentAgreement;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgreementFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_templates_but_only_super_admin_can_delete(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->post(route('agreements.templates.store'), [
                'name' => 'Residential Standard',
                'status' => 'active',
                'body_html' => '<p>Hello {{tenant_name}}</p>',
            ])
            ->assertRedirect();

        $template = AgreementTemplate::query()->firstOrFail();

        $this->actingAs($manager)
            ->delete(route('agreements.templates.destroy', $template))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->delete(route('agreements.templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseCount('agreement_templates', 0);
    }

    public function test_generating_new_agreement_voids_previous_unsigned_and_public_signing_marks_signed(): void
    {
        Storage::fake('public');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease();

        $template = AgreementTemplate::query()->create([
            'name' => 'Lease Template',
            'body_html' => '<p>{{tenant_name}} agrees for {{property_name}} {{unit_number}} at {{monthly_rent}}</p>',
            'status' => 'active',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.store', $lease), [
                'template_id' => $template->id,
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.store', $lease), [
                'template_id' => $template->id,
                'manual_content' => '<p>Override agreement</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('rent_agreements', 2);
        $this->assertDatabaseHas('rent_agreements', [
            'lease_id' => $lease->id,
            'status' => 'voided',
        ]);
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.agreement_generated',
        ]);

        $agreement = RentAgreement::query()->where('lease_id', $lease->id)->where('status', 'generated')->firstOrFail();

        $this->get(route('agreements.public.show', $agreement->token))
            ->assertOk()
            ->assertSee('digital agreement is for initial acceptance only', false);

        $this->post(route('agreements.public.sign', $agreement->token), [
            'signature_label' => 'Tenant Signature',
        ])->assertRedirect();

        $this->assertDatabaseHas('rent_agreements', [
            'id' => $agreement->id,
            'status' => 'signed',
            'signature_label' => 'Tenant Signature',
        ]);
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.agreement_signed',
        ]);

        $signedAgreement = $agreement->fresh();
        $this->assertNotNull($signedAgreement->signed_pdf_path);
        $this->assertNotNull($signedAgreement->signed_pdf_hash);
        $this->assertTrue(Storage::disk('public')->exists((string) $signedAgreement->signed_pdf_path));
    }

    public function test_integrity_verification_detects_tampering(): void
    {
        Storage::fake('public');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease();

        $template = AgreementTemplate::query()->create([
            'name' => 'Integrity Template',
            'body_html' => '<p>{{tenant_name}} agreement</p>',
            'status' => 'active',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.store', $lease), ['template_id' => $template->id])
            ->assertRedirect();

        $agreement = RentAgreement::query()->where('lease_id', $lease->id)->latest()->firstOrFail();

        $this->post(route('agreements.public.sign', $agreement->token), [
            'signature_label' => 'Integrity Tester',
        ])->assertRedirect();

        $agreement = $agreement->fresh();

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.verify-integrity', [$lease, $agreement]))
            ->assertRedirect();

        $this->assertDatabaseHas('rent_agreements', [
            'id' => $agreement->id,
            'integrity_check_status' => 'verified',
        ]);
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.agreement_integrity_verified',
        ]);

        Storage::disk('public')->put((string) $agreement->signed_pdf_path, 'tampered binary');

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.verify-integrity', [$lease, $agreement]))
            ->assertRedirect();

        $this->assertDatabaseHas('rent_agreements', [
            'id' => $agreement->id,
            'integrity_check_status' => 'tampered',
        ]);
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.agreement_integrity_failed',
        ]);
    }

    public function test_super_admin_can_upload_and_review_notarized_agreement(): void
    {
        Storage::fake('public');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease();

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.notarized.store', $lease), [
                'document' => UploadedFile::fake()->create('notarized-copy.pdf', 128, 'application/pdf'),
                'review_notes' => 'Uploaded after tenant signing.',
            ])->assertRedirect(route('leases.agreement.show', $lease));

        $this->assertDatabaseHas('notarized_agreements', [
            'lease_id' => $lease->id,
            'status' => 'uploaded',
            'uploaded_by' => $superAdmin->id,
            'original_name' => 'notarized-copy.pdf',
        ]);

        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.notarized_agreement_uploaded',
        ]);

        $notarizedAgreement = $lease->notarizedAgreements()->firstOrFail();

        $this->actingAs($superAdmin)
            ->patch(route('leases.agreement.notarized.update', [$lease, $notarizedAgreement]), [
                'status' => 'verified',
                'review_notes' => 'All pages verified with original hardcopy.',
            ])->assertRedirect(route('leases.agreement.show', $lease));

        $this->assertDatabaseHas('notarized_agreements', [
            'id' => $notarizedAgreement->id,
            'status' => 'verified',
            'reviewed_by' => $superAdmin->id,
            'review_notes' => 'All pages verified with original hardcopy.',
        ]);

        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $lease->unit->property_id,
            'event' => 'property.notarized_agreement_status_updated',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('leases.agreement.notarized.download', [$lease, $notarizedAgreement]))
            ->assertOk();
    }

    public function test_public_signing_sends_signed_pdf_copy_to_tenant_and_logs_delivery(): void
    {
        Storage::fake('public');
        Notification::fake();

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create(['email' => 'tenant-agreement-copy@example.test']);
        $tenantUser->assignRole('tenant');

        $lease = $this->createActiveLease($tenantUser);

        $template = AgreementTemplate::query()->create([
            'name' => 'Tenant Copy Template',
            'body_html' => '<p>{{tenant_name}} agreement copy</p>',
            'status' => 'active',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('leases.agreement.store', $lease), ['template_id' => $template->id])
            ->assertRedirect();

        $agreement = RentAgreement::query()->where('lease_id', $lease->id)->latest()->firstOrFail();

        $this->post(route('agreements.public.sign', $agreement->token), [
            'signature_label' => 'Tenant Signature',
        ])->assertRedirect();

        Notification::assertSentTo($tenantUser, SignedAgreementCopyNotification::class);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'agreement_signed_tenant_copy',
            'status' => 'sent',
            'recipient_email' => 'tenant-agreement-copy@example.test',
        ]);
    }

    private function createActiveLease(?User $tenantUser = null): Lease
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser?->id,
            'email' => $tenantUser?->email,
        ]);
        $actor = User::factory()->create();

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-04-01',
            'end_on' => '2027-03-31',
            'rent_amount' => 15000,
            'billing_day' => 1,
            'grace_period_days' => 0,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}