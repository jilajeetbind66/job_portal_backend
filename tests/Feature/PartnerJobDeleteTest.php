<?php
namespace Tests\Feature;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerJobDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createPartner(): Partner
    {
        $unique = Str::lower(Str::random(10));

        return Partner::create([
            'name' => 'Delete Test Partner ' . $unique,
            'slug' => 'delete-test-' . $unique,
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
    }

    private function createPartnerUser(
        Partner $partner,
        string $role = 'partner_admin'
    ): User {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createJob(Partner $partner, User $user): Job
    {
        $unique = Str::lower(Str::random(10));

        return Job::create([
            'partner_id' => $partner->id,
            'posted_by' => $user->id,
            'title' => 'Test Job ' . $unique,
            'slug' => 'test-job-' . $unique,
            'description' => 'Job created for delete API testing.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Remote',
            'min_exp' => 0,
            'max_exp' => 3,
            'salary_min' => 20000,
            'salary_max' => 50000,
            'openings' => 1,
            'status' => 'draft',
        ]);
    }

    public function test_partner_admin_can_delete_own_job(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$job->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Job deleted successfully.',
            ]);

        $this->assertDatabaseMissing('job_postings', [
            'id' => $job->id,
        ]);
    }

    public function test_deleting_job_creates_audit_log(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $user);

        $jobId = $job->id;

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$jobId}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'partner_id' => $partner->id,
            'action' => 'job.deleted',
            'subject_type' => $job->getMorphClass(),
            'subject_id' => $jobId,
        ]);
    }

    public function test_partner_cannot_delete_another_partners_job(): void
    {
        $partnerA = $this->createPartner();
        $partnerB = $this->createPartner();

        $userA = $this->createPartnerUser($partnerA);
        $userB = $this->createPartnerUser($partnerB);

        $jobB = $this->createJob($partnerB, $userB);

        $response = $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$jobB->id}");

        // Tenant global scope should hide another partner's job.
        $response->assertNotFound();

        $this->assertDatabaseHas('job_postings', [
            'id' => $jobB->id,
            'partner_id' => $partnerB->id,
        ]);
    }

    public function test_candidate_cannot_delete_partner_job(): void
    {
        $partner = $this->createPartner();
        $owner = $this->createPartnerUser($partner);
        $candidate = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $candidate->assignRole('candidate');

        $job = $this->createJob($partner, $owner);

        $response = $this->actingAs($candidate, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$job->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
        ]);
    }

    public function test_user_without_partner_cannot_delete_job(): void
    {
        $partner = $this->createPartner();
        $owner = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $owner);

        $user = User::factory()->create([
            'partner_id' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('partner_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$job->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
        ]);
    }

    public function test_guest_cannot_delete_partner_job(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner);
        $job = $this->createJob($partner, $user);

        $this->deleteJson("/api/v1/partner/jobs/{$job->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
        ]);
    }

    public function test_partner_recruiter_can_delete_own_job(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser(
            $partner,
            'partner_recruiter'
        );

        $job = $this->createJob($partner, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/partner/jobs/{$job->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('job_postings', [
            'id' => $job->id,
        ]);
    }

    public function test_deleting_nonexistent_job_returns_404(): void
    {
        $partner = $this->createPartner();
        $user = $this->createPartnerUser($partner);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/partner/jobs/99999999')
            ->assertNotFound();
    }
}
