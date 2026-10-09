<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobModerationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'partner_id' => null,
        ]);

        $user->assignRole('super_admin');

        return $user;
    }

    private function createPartnerAdmin(Partner $partner): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'partner_id' => $partner->id,
        ]);

        $user->assignRole('partner_admin');

        return $user;
    }

    private function createCandidate(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'partner_id' => null,
        ]);

        $user->assignRole('candidate');

        return $user;
    }

    private function createPartner(array $attributes = []): Partner
    {
        return Partner::create(array_merge([
            'name' => 'Test Partner',
            'slug' => 'test-partner-' . uniqid(),
            'industry' => 'IT',
            'status' => 'active',
            'max_active_jobs' => 10,
        ], $attributes));
    }

    private function createJob(
        Partner $partner,
        User $postedBy,
        array $attributes = []
    ): Job {
        return Job::create(array_merge([
            'partner_id' => $partner->id,
            'posted_by' => $postedBy->id,
            'title' => 'Laravel Developer',
            'slug' => 'laravel-developer-' . uniqid(),
            'description' => 'Laravel developer required.',
            'employment_type' => 'full_time',
            'work_mode' => 'onsite',
            'location' => 'Noida',
            'min_exp' => 2,
            'max_exp' => 5,
            'salary_min' => 40000,
            'salary_max' => 70000,
            'openings' => 2,
            'status' => 'pending_review',
        ], $attributes));
    }

    public function test_super_admin_can_view_jobs(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $this->createJob($partner, $recruiter);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/jobs');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Laravel Developer');
    }

    public function test_super_admin_can_view_single_job(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $job = $this->createJob($partner, $recruiter);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/jobs/{$job->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_super_admin_can_publish_job(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $job = $this->createJob($partner, $recruiter);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'published',
                'note' => 'Job approved.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $job->refresh();

        $this->assertSame('published', $job->status);
        $this->assertNotNull($job->published_at);
    }

    public function test_super_admin_can_reject_job(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $job = $this->createJob($partner, $recruiter);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'rejected',
                'note' => 'Job requires correction.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $job->refresh()->status);
    }

    public function test_super_admin_can_close_job(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $job = $this->createJob(
            $partner,
            $recruiter,
            [
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'closed',
                'note' => 'Position closed.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame('closed', $job->refresh()->status);
    }

    public function test_partner_admin_cannot_moderate_job(): void
    {
        $partner = $this->createPartner();

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $job = $this->createJob($partner, $partnerAdmin);

        $response = $this->actingAs($partnerAdmin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'published',
            ]);

        $response->assertForbidden();

        $this->assertSame('pending_review', $job->refresh()->status);
    }

    public function test_candidate_cannot_moderate_job(): void
    {
        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $candidate = $this->createCandidate();

        $job = $this->createJob($partner, $recruiter);

        $response = $this->actingAs($candidate)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'published',
            ]);

        $response->assertForbidden();

        $this->assertSame('pending_review', $job->refresh()->status);
    }

    public function test_guest_cannot_access_job_moderation(): void
    {
        $response = $this->getJson('/api/v1/admin/jobs');

        $response->assertUnauthorized();
    }

    public function test_moderation_validates_status(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $job = $this->createJob($partner, $recruiter);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'invalid_status',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('pending_review', $job->refresh()->status);
    }

    public function test_moderation_does_not_accept_partner_id_from_request(): void
    {
        $admin = $this->createSuperAdmin();

        $partnerA = $this->createPartner([
            'name' => 'Partner A',
            'slug' => 'partner-a-' . uniqid(),
        ]);

        $partnerB = $this->createPartner([
            'name' => 'Partner B',
            'slug' => 'partner-b-' . uniqid(),
        ]);

        $recruiter = $this->createPartnerAdmin($partnerA);

        $job = $this->createJob($partnerA, $recruiter);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/jobs/{$job->id}/moderate", [
                'status' => 'published',
                'partner_id' => $partnerB->id,
            ])
            ->assertOk();

        $job->refresh();

        $this->assertSame($partnerA->id, $job->partner_id);
        $this->assertSame('published', $job->status);
    }

    public function test_super_admin_can_filter_jobs_by_status(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner();

        $recruiter = $this->createPartnerAdmin($partner);

        $this->createJob($partner, $recruiter, [
            'status' => 'pending_review',
        ]);

        $this->createJob($partner, $recruiter, [
            'title' => 'Published Developer',
            'slug' => 'published-developer-' . uniqid(),
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/jobs?status=pending_review');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending_review');
    }
}