<?php
namespace Tests\Feature;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class JobTenantAccessTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    public function test_partner_admin_cannot_access_another_partners_job_by_id(): void
    {
        $partnerA = Partner::create([
            'name' => 'Company A',
            'slug' => 'company-a-test',
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
        $partnerB = Partner::create([
            'name' => 'Company B',
            'slug' => 'company-b-test',
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
        $adminA = User::factory()->create([
            'partner_id' => $partnerA->id,
            'status' => 'active',
        ]);
        $adminA->assignRole('partner_admin');
        $recruiterB = User::factory()->create([
            'partner_id' => $partnerB->id,
            'status' => 'active',
        ]);
        $recruiterB->assignRole('partner_admin');
        // Create Partner B's job while no user is authenticated.
        $jobB = Job::create([
            'partner_id' => $partnerB->id,
            'posted_by' => $recruiterB->id,
            'title' => 'Company B Private Job',
            'slug' => 'company-b-private-job',
            'description' => 'Private job belonging to Company B.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Delhi',
            'min_exp' => 1,
            'max_exp' => 3,
            'salary_min' => 30000,
            'salary_max' => 50000,
            'openings' => 1,
            'status' => 'pending_review',
        ]);
        // Partner A must not access Partner B's job by its ID.
        $response = $this->actingAs($adminA)
            ->getJson("/api/v1/admin/jobs/{$jobB->id}");
        $this->assertContains(
            $response->status(),
            [403, 404],
            'A partner admin must not access another partner job.'
        );
    }


public function test_partner_admin_cannot_moderate_another_partners_job(): void
{
    $partnerA = Partner::create([
        'name' => 'Company A',
        'slug' => 'company-a-moderation-test',
        'status' => 'active',
        'max_active_jobs' => 10,
    ]);

    $partnerB = Partner::create([
        'name' => 'Company B',
        'slug' => 'company-b-moderation-test',
        'status' => 'active',
        'max_active_jobs' => 10,
    ]);

    $adminA = User::factory()->create([
        'partner_id' => $partnerA->id,
        'status' => 'active',
    ]);
    $adminA->assignRole('partner_admin');

    $recruiterB = User::factory()->create([
        'partner_id' => $partnerB->id,
        'status' => 'active',
    ]);
    $recruiterB->assignRole('partner_admin');

    // Partner B ki job create karo.
    $jobB = Job::create([
        'partner_id' => $partnerB->id,
        'posted_by' => $recruiterB->id,
        'title' => 'Company B Restricted Job',
        'slug' => 'company-b-restricted-job',
        'description' => 'Restricted job for tenant security testing.',
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'location' => 'Delhi',
        'min_exp' => 1,
        'max_exp' => 3,
        'salary_min' => 30000,
        'salary_max' => 50000,
        'openings' => 1,
        'status' => 'pending_review',
    ]);

    // Partner A job ko publish karne ki koshish karta hai.
    $response = $this->actingAs($adminA)
        ->patchJson("/api/v1/admin/jobs/{$jobB->id}/moderate", [
            'status' => 'published',
            'note' => 'Unauthorized moderation attempt.',
        ]);

    $this->assertContains(
        $response->status(),
        [403, 404],
        'A partner admin must not moderate another partner job.'
    );

    // Confirm karo ki job ka status change nahi hua.
    $this->assertSame(
        'pending_review',
        $jobB->fresh()->status
    );
}

public function test_super_admin_with_partner_id_can_access_other_partners_jobs(): void
{
    $partnerA = Partner::create([
        'name' => 'Super Admin Partner A',
        'slug' => 'super-admin-partner-a',
        'status' => 'active',
        'max_active_jobs' => 10,
    ]);

    $partnerB = Partner::create([
        'name' => 'Super Admin Partner B',
        'slug' => 'super-admin-partner-b',
        'status' => 'active',
        'max_active_jobs' => 10,
    ]);

    $admin = User::factory()->create([
        'partner_id' => $partnerA->id,
        'status' => 'active',
    ]);

    $admin->assignRole('super_admin');

    $recruiterB = User::factory()->create([
        'partner_id' => $partnerB->id,
        'status' => 'active',
    ]);

    $recruiterB->assignRole('partner_admin');

    // Create Partner B's job before authenticating as super admin.
    $jobB = Job::create([
        'partner_id' => $partnerB->id,
        'posted_by' => $recruiterB->id,
        'title' => 'Partner B Cross-Tenant Job',
        'slug' => 'partner-b-cross-tenant-job',
        'description' => 'Cross-tenant moderation test.',
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'location' => 'Delhi',
        'min_exp' => 1,
        'max_exp' => 3,
        'salary_min' => 30000,
        'salary_max' => 50000,
        'openings' => 1,
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin);

    // Super admin should see jobs across all partners.
    $this->getJson('/api/v1/admin/jobs')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $jobB->id,
            'title' => 'Partner B Cross-Tenant Job',
        ]);

    // Super admin should open another partner's job.
    $this->getJson("/api/v1/admin/jobs/{$jobB->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $jobB->id);

    // Super admin should be able to moderate that job.
    $this->patchJson("/api/v1/admin/jobs/{$jobB->id}/moderate", [
        'status' => 'published',
        'note' => 'Approved by super admin.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $this->assertSame('published', $jobB->fresh()->status);
}
}
