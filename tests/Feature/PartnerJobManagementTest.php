<?php
namespace Tests\Feature;
use App\Models\AuditLog;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerJobManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createPartner(array $attributes = []): Partner
    {
        static $counter = 0;
        $counter++;

        return Partner::create(array_merge([
            'name' => "Test Company {$counter}",
            'slug' => "test-company-{$counter}",
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
            'title' => "Test Developer {$counter}",
            'slug' => "test-developer-{$counter}",
            'description' => 'A test job description.',
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


        public function test_partner_admin_only_sees_own_jobs(): void
        {
            $partnerA = $this->createPartner();
            $partnerB = $this->createPartner();

            $adminA = $this->createPartnerUser($partnerA);
            $adminB = $this->createPartnerUser($partnerB);

            $jobA = $this->createJob($partnerA, $adminA);
            $jobB = $this->createJob($partnerB, $adminB);

            // Partner A ki job rejected hai aur uska reason available hai.
            $jobA->update([
                'status' => 'rejected',
                'rejection_note' => 'Please correct the job description.',
            ]);

            $response = $this->actingAs($adminA, 'sanctum')
                ->getJson('/api/v1/partner/jobs');

            $response->assertOk()
                ->assertJsonFragment([
                    'id' => $jobA->id,
                    'status' => 'rejected',
                    'rejection_note' => 'Please correct the job description.',
                ])
                ->assertJsonMissing([
                    'id' => $jobB->id,
                ]);
        }


    public function test_partner_recruiter_can_create_a_draft_job(): void
    {
        $partner = $this->createPartner();
        $recruiter = $this->createPartnerUser(
            $partner,
            'partner_recruiter'
        );

        $response = $this->actingAs($recruiter)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'Laravel Developer',
                'slug' => 'laravel-developer',
                'description' => 'Develop and maintain Laravel APIs.',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'location' => 'Noida',
                'min_exp' => 2,
                'max_exp' => 5,
                'salary_min' => 40000,
                'salary_max' => 70000,
                'openings' => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.partner_id', $partner->id)
            ->assertJsonPath('data.posted_by', $recruiter->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('job_postings', [
            'partner_id' => $partner->id,
            'posted_by' => $recruiter->id,
            'slug' => 'laravel-developer',
            'status' => 'draft',
        ]);
    }

    public function test_client_cannot_spoof_job_ownership_or_status(): void
    {
        $partnerA = $this->createPartner();
        $partnerB = $this->createPartner();

        $adminA = $this->createPartnerUser($partnerA);

        $response = $this->actingAs($adminA)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'Secure Job',
                'slug' => 'secure-job',
                'description' => 'A secure job description.',
                'employment_type' => 'full_time',
                'work_mode' => 'onsite',
                'location' => 'Delhi',
                'min_exp' => 1,
                'max_exp' => 3,
                'openings' => 1,

                // Untrusted values must be ignored.
                'partner_id' => $partnerB->id,
                'posted_by' => 999999,
                'status' => 'published',
                'published_at' => now()->toISOString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.partner_id', $partnerA->id)
            ->assertJsonPath('data.posted_by', $adminA->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('job_postings', [
            'slug' => 'secure-job',
            'partner_id' => $partnerA->id,
            'posted_by' => $adminA->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function test_candidate_cannot_list_or_create_partner_jobs(): void
    {
        $partner = $this->createPartner();

        $candidate = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $candidate->assignRole('candidate');

        $this->actingAs($candidate)
            ->getJson('/api/v1/partner/jobs')
            ->assertForbidden();

        $this->actingAs($candidate)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'Unauthorized Job',
                'slug' => 'unauthorized-job',
                'description' => 'Should not be created.',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'openings' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_postings', [
            'slug' => 'unauthorized-job',
        ]);
    }

    public function test_user_without_partner_cannot_manage_partner_jobs(): void
    {
        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
        ]);

        $user->assignRole('partner_admin');

        $this->actingAs($user)
            ->getJson('/api/v1/partner/jobs')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'No Tenant Job',
                'slug' => 'no-tenant-job',
                'description' => 'Should not be created.',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'openings' => 1,
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_partner_job_routes(): void
    {
        $this->getJson('/api/v1/partner/jobs')
            ->assertUnauthorized();

        $this->postJson('/api/v1/partner/jobs', [])
            ->assertUnauthorized();
    }

    public function test_duplicate_slug_is_rejected_within_same_partner(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);

        $this->createJob($partner, $admin, [
            'slug' => 'duplicate-job',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'Another Developer',
                'slug' => 'duplicate-job',
                'description' => 'Another job description.',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'openings' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_partner_job_list_supports_status_and_search_filters(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);

        $draftJob = $this->createJob($partner, $admin, [
            'title' => 'Laravel Backend Developer',
            'slug' => 'laravel-backend-filter-test',
            'status' => 'draft',
        ]);

        $publishedJob = $this->createJob($partner, $admin, [
            'title' => 'React Frontend Developer',
            'slug' => 'react-frontend-filter-test',
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/partner/jobs?status=draft&search=Laravel')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $draftJob->id,
                'title' => 'Laravel Backend Developer',
            ])
            ->assertJsonMissing([
                'id' => $publishedJob->id,
            ]);
    }

    public function test_creating_job_writes_an_audit_log(): void
    {
        $partner = $this->createPartner();
        $admin = $this->createPartnerUser($partner);

        $this->actingAs($admin)
            ->postJson('/api/v1/partner/jobs', [
                'title' => 'Audit Log Developer',
                'slug' => 'audit-log-developer',
                'description' => 'A job used for audit testing.',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'openings' => 1,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'partner_id' => $partner->id,
            'action' => 'job.created',
        ]);
    }
}
