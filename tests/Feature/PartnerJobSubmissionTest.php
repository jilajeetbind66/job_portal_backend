<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerJobSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_partner_admin_can_submit_own_draft_job_for_review(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner, 'partner_admin');
        $job = $this->createJob($partner, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath(
                'message',
                'Job submitted for review successfully.'
            );

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'partner_id' => $partner->id,
            'status' => 'pending_review',
            'published_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'partner_id' => $partner->id,
            'action' => 'job.submitted_for_review',
            'subject_id' => $job->id,
        ]);
    }

    public function test_partner_recruiter_can_submit_own_draft_job_for_review(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner, 'partner_recruiter');
        $job = $this->createJob($partner, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_partner_cannot_submit_another_partners_job(): void
    {
        $partnerOne = $this->createPartner();
        $partnerTwo = $this->createPartner();

        $user = $this->createPartnerUser($partnerOne, 'partner_admin');
        $otherUser = $this->createPartnerUser($partnerTwo, 'partner_admin');

        $job = $this->createJob($partnerTwo, $otherUser);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertNotFound();

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'status' => 'draft',
        ]);
    }

    public function test_non_draft_job_cannot_be_submitted_for_review(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner, 'partner_admin');
        $job = $this->createJob($partner, $user);

        $job->update([
            'status' => 'published',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only draft or rejected jobs can be submitted for review.'
            );

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'status' => 'published',
        ]);
    }

    public function test_candidate_cannot_submit_partner_job_for_review(): void
    {
        $partner = $this->createPartner();
        $owner = $this->createPartnerUser($partner, 'partner_admin');
        $candidate = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $candidate->assignRole('candidate');

        $job = $this->createJob($partner, $owner);

        $response = $this->actingAs($candidate, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertForbidden();
    }

    public function test_user_without_partner_cannot_submit_job_for_review(): void
    {
        $partner = $this->createPartner();
        $owner = $this->createPartnerUser($partner, 'partner_admin');
        $job = $this->createJob($partner, $owner);

        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('partner_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

        $response->assertForbidden();
    }

    public function test_guest_cannot_submit_job_for_review(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner, 'partner_admin');
        $job = $this->createJob($partner, $user);

        $response = $this->postJson(
            "/api/v1/partner/jobs/{$job->id}/submit-review"
        );

        $response->assertUnauthorized();
    }

    public function test_partner_cannot_publish_job_by_spoofing_request_status(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner, 'partner_admin');
        $job = $this->createJob($partner, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/partner/jobs/{$job->id}/submit-review",
                ['status' => 'published']
            );

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_review');

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'status' => 'pending_review',
        ]);
    }

    private function createPartner(): Partner
    {
        $unique = Str::lower(Str::random(10));

        return Partner::create([
            'name' => 'Test Partner ' . $unique,
            'slug' => 'test-partner-' . $unique,
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
    }

    private function createPartnerUser(
        Partner $partner,
        string $role
    ): User {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createJob(
        Partner $partner,
        User $user
    ): Job {
        $unique = Str::lower(Str::random(10));

        return Job::create([
            'partner_id' => $partner->id,
            'posted_by' => $user->id,
            'title' => 'Test Developer ' . $unique,
            'slug' => 'test-developer-' . $unique,
            'description' => 'This is a test job description.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Remote',
            'min_exp' => 0,
            'max_exp' => 3,
            'salary_min' => 30000,
            'salary_max' => 60000,
            'openings' => 1,
            'status' => 'draft',
        ]);
    }

    

    public function test_partner_can_resubmit_rejected_job_for_review(): void
    {
    $partner = $this->createPartner();
    $user = $this->createPartnerUser($partner, 'partner_admin');
    $job = $this->createJob($partner, $user);

    // Set the rejection status and reason.
    $job->update([
        'status' => 'rejected',
        'rejection_note' => 'Please correct the job description.',
    ]);

    // Partner corrects the job before resubmitting.
    $job->update([
        'description' => 'Updated job description after rejection.',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/partner/jobs/{$job->id}/submit-review");

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending_review')
        ->assertJsonPath('data.rejection_note', null)
        ->assertJsonPath(
            'message',
            'Job submitted for review successfully.'
        );

    $this->assertDatabaseHas('job_postings', [
        'id' => $job->id,
        'status' => 'pending_review',
        'description' => 'Updated job description after rejection.',
        'rejection_note' => null,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'partner_id' => $partner->id,
        'action' => 'job.submitted_for_review',
        'subject_id' => $job->id,
    ]);
}


}
