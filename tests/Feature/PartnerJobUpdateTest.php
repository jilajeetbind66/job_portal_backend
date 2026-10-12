<?php
namespace Tests\Feature;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerJobUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createPartner(array $attributes = []): Partner
    {
        static $counter = 0;
        $counter++;

        return Partner::create(array_merge([
            'name' => "Update Test Company {$counter}",
            'slug' => "update-test-company-{$counter}",
            'status' => 'active',
            'max_active_jobs' => 10,
        ], $attributes));
    }

    private function createPartnerUser(
        Partner $partner,
        string $role = 'partner_admin'
    ): User {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createJob(
        Partner $partner,
        User $user,
        array $attributes = []
    ): Job {
        static $counter = 0;
        $counter++;

        return Job::create(array_merge([
            'partner_id' => $partner->id,
            'posted_by' => $user->id,
            'title' => "Update Test Developer {$counter}",
            'slug' => "update-test-developer-{$counter}",
            'description' => 'A job description for update testing.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Noida',
            'min_exp' => 1,
            'max_exp' => 3,
            'salary_min' => 30000,
            'salary_max' => 50000,
            'openings' => 1,
            'status' => 'draft',
        ], $attributes));
    }

    public function test_partner_admin_can_update_own_job(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $this->actingAs($admin)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'Senior Laravel Developer',
                'location' => 'Delhi',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.title', 'Senior Laravel Developer')
            ->assertJsonPath('data.location', 'Delhi')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'partner_id' => $partner->id,
            'title' => 'Senior Laravel Developer',
            'location' => 'Delhi',
            'status' => 'draft',
        ]);
    }

    public function test_partner_admin_can_update_own_job_using_put(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $this->actingAs($admin)
            ->putJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'PUT Updated Developer',
                'slug' => 'put-updated-developer',
                'description' => 'Updated job description.',
                'employment_type' => 'full_time',
                'work_mode' => 'hybrid',
                'location' => 'Delhi',
                'min_exp' => 2,
                'max_exp' => 4,
                'salary_min' => 50000,
                'salary_max' => 80000,
                'openings' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'PUT Updated Developer')
            ->assertJsonPath('data.slug', 'put-updated-developer')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'partner_id' => $partner->id,
            'title' => 'PUT Updated Developer',
            'slug' => 'put-updated-developer',
            'status' => 'draft',
        ]);
    }

    public function test_partner_can_partially_update_job_without_changing_other_fields(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);

        $job = $this->createJob($partner, $admin, [
            'title' => 'Original Developer',
            'location' => 'Noida',
            'openings' => 2,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'Updated Developer',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Developer')
            ->assertJsonPath('data.location', 'Noida')
            ->assertJsonPath('data.openings', 2);

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'title' => 'Updated Developer',
            'location' => 'Noida',
            'openings' => 2,
        ]);
    }

    public function test_partner_cannot_update_another_partners_job(): void
    {
        $partnerA = $this->createPartner();
        $partnerB = $this->createPartner();

        $adminA = $this->createPartnerUser($partnerA);
        $adminB = $this->createPartnerUser($partnerB);

        $jobB = $this->createJob($partnerB, $adminB, [
            'title' => 'Partner B Private Job',
        ]);

        $this->actingAs($adminA)
            ->patchJson("/api/v1/partner/jobs/{$jobB->id}", [
                'title' => 'Unauthorized Update',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('job_postings', [
            'id' => $jobB->id,
            'partner_id' => $partnerB->id,
            'title' => 'Partner B Private Job',
        ]);
    }

    public function test_candidate_cannot_update_partner_job(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $candidate = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $candidate->assignRole('candidate');

        $this->actingAs($candidate)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'Unauthorized Candidate Update',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_postings', [
            'id' => $job->id,
            'title' => 'Unauthorized Candidate Update',
        ]);
    }

    public function test_user_without_partner_cannot_update_partner_job(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $user->assignRole('partner_admin');

        $this->actingAs($user)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'No Tenant Update',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_postings', [
            'id' => $job->id,
            'title' => 'No Tenant Update',
        ]);
    }

    public function test_guest_cannot_update_partner_job(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $this->patchJson("/api/v1/partner/jobs/{$job->id}", [
            'title' => 'Guest Update',
        ])->assertUnauthorized();
    }


public function test_client_cannot_change_job_ownership_or_status(): void
{
    $partnerA = $this->createPartner();
    $partnerB = $this->createPartner();

    $adminA = $this->createPartnerUser($partnerA);

    $job = $this->createJob($partnerA, $adminA, [
        'status' => 'draft',
    ]);

    $this->actingAs($adminA)
        ->patchJson("/api/v1/partner/jobs/{$job->id}", [
            'title' => 'Secure Updated Job',
            'partner_id' => $partnerB->id,
            'posted_by' => 999999,
            'status' => 'published',
            'published_at' => now()->toISOString(),
            'rejection_note' => 'Trying to bypass moderation',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Secure Updated Job')
        ->assertJsonPath('data.partner_id', $partnerA->id)
        ->assertJsonPath('data.posted_by', $adminA->id)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.rejection_note', null);

    $this->assertDatabaseHas('job_postings', [
        'id' => $job->id,
        'partner_id' => $partnerA->id,
        'posted_by' => $adminA->id,
        'title' => 'Secure Updated Job',
        'status' => 'draft',
        'published_at' => null,
        'rejection_note' => null,
    ]);
}


    public function test_update_rejects_invalid_job_data(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $this->actingAs($admin)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => '',
                'openings' => 0,
                'work_mode' => 'spaceship',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'openings',
                'work_mode',
            ]);
    }

    public function test_update_rejects_duplicate_slug_for_same_partner(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);

        $this->createJob($partner, $admin, [
            'slug' => 'existing-update-slug',
        ]);

        $jobToUpdate = $this->createJob($partner, $admin, [
            'slug' => 'another-update-slug',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/partner/jobs/{$jobToUpdate->id}", [
                'slug' => 'existing-update-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_successful_job_update_creates_audit_log(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $admin);

        $this->actingAs($admin)
            ->patchJson("/api/v1/partner/jobs/{$job->id}", [
                'title' => 'Audited Job Update',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'partner_id' => $partner->id,
            'actor_id' => $admin->id,
            'action' => 'job.updated',
            'subject_type' => $job->getMorphClass(),
            'subject_id' => $job->id,
        ]);
    }
}
