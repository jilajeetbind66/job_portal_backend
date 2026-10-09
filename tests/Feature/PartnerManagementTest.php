<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $user->assignRole('super_admin');

        return $user;
    }

    private function createPartnerAdmin(Partner $partner): User
    {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_admin');

        return $user;
    }

    private function createCandidate(): User
    {
        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        return $user;
    }

    private function createPartner(array $attributes = []): Partner
    {
        return Partner::create(array_merge([
            'name' => 'Test Partner',
            'slug' => 'test-partner-' . Str::lower(Str::random(6)),
            'industry' => 'Information Technology',
            'website' => 'https://example.com',
            'about' => 'Test partner company.',
            'status' => 'active',
            'max_active_jobs' => 50,
            'created_by' => null,
        ], $attributes));
    }

    public function test_super_admin_can_view_partners(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner([
            'name' => 'ABC Technologies',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/partners');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $partner->id)
            ->assertJsonPath('data.0.name', 'ABC Technologies');
    }

    public function test_super_admin_can_create_partner(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/partners', [
                'name' => 'New Technology Partner',
                'slug' => 'new-technology-partner',
                'industry' => 'Information Technology',
                'website' => 'https://example.com',
                'about' => 'A technology company.',
                'max_active_jobs' => 100,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Technology Partner')
            ->assertJsonPath('data.slug', 'new-technology-partner')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.created_by', $admin->id);

        $this->assertDatabaseHas('partners', [
            'name' => 'New Technology Partner',
            'slug' => 'new-technology-partner',
            'created_by' => $admin->id,
            'status' => 'active',
        ]);
    }

    public function test_non_super_admin_cannot_create_partner(): void
    {
        $candidate = $this->createCandidate();

        $response = $this->actingAs($candidate)
            ->postJson('/api/v1/admin/partners', [
                'name' => 'Unauthorized Partner',
                'slug' => 'unauthorized-partner',
                'industry' => 'Information Technology',
                'max_active_jobs' => 10,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('partners', [
            'slug' => 'unauthorized-partner',
        ]);
    }

    public function test_super_admin_can_view_single_partner(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner([
            'name' => 'Single Partner',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/partners/{$partner->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $partner->id)
            ->assertJsonPath('data.name', 'Single Partner');
    }

    public function test_partner_admin_can_view_own_partner(): void
    {
        $partner = $this->createPartner([
            'name' => 'Own Partner',
        ]);

        $admin = $this->createPartnerAdmin($partner);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/partners/{$partner->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $partner->id);
    }

    public function test_partner_admin_cannot_view_another_partner(): void
    {
        $partnerA = $this->createPartner([
            'name' => 'Partner A',
        ]);

        $partnerB = $this->createPartner([
            'name' => 'Partner B',
        ]);

        $adminA = $this->createPartnerAdmin($partnerA);

        $response = $this->actingAs($adminA)
            ->getJson("/api/v1/admin/partners/{$partnerB->id}");

        $response->assertForbidden();
    }

    public function test_partner_admin_can_update_own_partner(): void
    {
        $partner = $this->createPartner([
            'name' => 'Old Partner Name',
        ]);

        $admin = $this->createPartnerAdmin($partner);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/partners/{$partner->id}", [
                'name' => 'Updated Partner Name',
                'industry' => 'Software',
                'max_active_jobs' => 75,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Partner Name')
            ->assertJsonPath('data.industry', 'Software')
            ->assertJsonPath('data.max_active_jobs', 75);

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'name' => 'Updated Partner Name',
            'industry' => 'Software',
            'max_active_jobs' => 75,
        ]);
    }

    public function test_partner_admin_cannot_update_another_partner(): void
    {
        $partnerA = $this->createPartner([
            'name' => 'Partner A',
        ]);

        $partnerB = $this->createPartner([
            'name' => 'Partner B',
        ]);

        $adminA = $this->createPartnerAdmin($partnerA);

        $response = $this->actingAs($adminA)
            ->putJson("/api/v1/admin/partners/{$partnerB->id}", [
                'name' => 'Hacked Partner Name',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('partners', [
            'id' => $partnerB->id,
            'name' => 'Partner B',
        ]);
    }

    public function test_super_admin_can_update_partner(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner([
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/partners/{$partner->id}", [
                'name' => 'New Name',
                'industry' => 'Automotive',
                'max_active_jobs' => 200,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.industry', 'Automotive')
            ->assertJsonPath('data.max_active_jobs', 200);
    }

    public function test_super_admin_can_suspend_partner(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner([
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/partners/{$partner->id}/status", [
                'status' => 'suspended',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'suspended',
        ]);
    }

    public function test_super_admin_can_activate_partner(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner([
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/partners/{$partner->id}/status", [
                'status' => 'active',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'active',
        ]);
    }

    public function test_partner_admin_cannot_change_partner_status(): void
    {
        $partner = $this->createPartner([
            'status' => 'active',
        ]);

        $admin = $this->createPartnerAdmin($partner);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/partners/{$partner->id}/status", [
                'status' => 'suspended',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'active',
        ]);
    }

    public function test_partner_creation_validates_required_fields(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/partners', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'max_active_jobs',
            ]);
    }

    public function test_partner_creation_rejects_duplicate_slug(): void
    {
        $admin = $this->createSuperAdmin();

        $this->createPartner([
            'slug' => 'duplicate-partner',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/partners', [
                'name' => 'Another Partner',
                'slug' => 'duplicate-partner',
                'max_active_jobs' => 50,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_created_by_cannot_be_manipulated_from_request(): void
    {
        $admin = $this->createSuperAdmin();

        $anotherUser = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $anotherUser->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/partners', [
                'name' => 'Secure Partner',
                'slug' => 'secure-partner',
                'max_active_jobs' => 25,
                'created_by' => $anotherUser->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_by', $admin->id);

        $this->assertDatabaseHas('partners', [
            'slug' => 'secure-partner',
            'created_by' => $admin->id,
        ]);
    }

    public function test_guest_cannot_access_partner_management(): void
    {
        $partner = $this->createPartner();

        $this->getJson('/api/v1/admin/partners')
            ->assertUnauthorized();

        $this->getJson("/api/v1/admin/partners/{$partner->id}")
            ->assertUnauthorized();
    }
}