<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatePortalTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createPartner(string $slug): Partner
    {
        return Partner::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
    }

    private function createCandidate(): User
    {
        $candidate = User::factory()->create(['partner_id' => null, 'status' => 'active']);
        $candidate->assignRole('candidate');

        return $candidate;
    }

    private function createPublishedJob(Partner $partner, User $poster, string $slug): Job
    {
        return Job::create([
            'partner_id' => $partner->id,
            'posted_by' => $poster->id,
            'title' => 'Published '.$slug,
            'slug' => $slug,
            'department' => 'Engineering',
            'description' => 'A published role for integration testing.',
            'requirements' => 'Laravel and API experience.',
            'skills' => ['PHP', 'Laravel'],
            'currency' => 'INR',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Remote',
            'min_exp' => 2,
            'max_exp' => 5,
            'salary_min' => 50000,
            'salary_max' => 80000,
            'openings' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_candidate_can_update_profile_save_job_apply_and_withdraw(): void
    {
        $partner = $this->createPartner('candidate-flow-company');
        $poster = User::factory()->create(['partner_id' => $partner->id, 'status' => 'active']);
        $poster->assignRole('partner_admin');
        $job = $this->createPublishedJob($partner, $poster, 'candidate-flow-role');
        $candidate = $this->createCandidate();

        $this->actingAs($candidate)
            ->getJson('/api/v1/candidate/profile')
            ->assertOk()
            ->assertJsonPath('data.name', $candidate->name)
            ->assertJsonPath('data.skills', []);

        $this->putJson('/api/v1/candidate/profile', [
            'name' => 'Profile Candidate',
            'headline' => 'Laravel Developer',
            'phone' => '9876543210',
            'location' => 'Delhi',
            'bio' => 'Backend engineer.',
            'skills' => ['PHP', 'Laravel', 'SQL'],
            'experience' => [['company' => 'Example', 'title' => 'Developer']],
            'education' => [['school' => 'Example University', 'degree' => 'BSc']],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Profile Candidate')
            ->assertJsonPath('data.skills.1', 'Laravel');

        $this->postJson('/api/v1/candidate/saved-jobs', ['job_id' => $job->id])
            ->assertCreated();
        $this->getJson('/api/v1/candidate/saved-jobs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $job->id);

        $applicationResponse = $this->postJson("/api/v1/candidate/jobs/{$job->id}/apply", [
            'cover_letter' => 'I am interested in this role.',
        ]);

        $applicationResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.job.company_name', $partner->name);

        $applicationId = $applicationResponse->json('data.id');

        $this->getJson('/api/v1/candidate/applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $applicationId)
            ->assertJsonPath('data.0.cover_letter', 'I am interested in this role.');

        $this->patchJson("/api/v1/candidate/applications/{$applicationId}/withdraw")
            ->assertOk();

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'status' => 'withdrawn',
        ]);

        $this->deleteJson("/api/v1/candidate/saved-jobs/{$job->id}")
            ->assertOk();
    }

    public function test_partner_can_review_only_its_applications_and_advance_status(): void
    {
        $partnerA = $this->createPartner('application-partner-a');
        $partnerB = $this->createPartner('application-partner-b');
        $adminA = User::factory()->create(['partner_id' => $partnerA->id, 'status' => 'active']);
        $adminA->assignRole('partner_admin');
        $adminB = User::factory()->create(['partner_id' => $partnerB->id, 'status' => 'active']);
        $adminB->assignRole('partner_admin');
        $jobA = $this->createPublishedJob($partnerA, $adminA, 'application-role-a');
        $jobB = $this->createPublishedJob($partnerB, $adminB, 'application-role-b');
        $candidate = $this->createCandidate();

        $this->actingAs($candidate)->postJson("/api/v1/candidate/jobs/{$jobA->id}/apply", [])->assertCreated();
        $this->postJson("/api/v1/candidate/jobs/{$jobB->id}/apply", [])->assertCreated();
        $applicationA = $candidate->id;

        $listResponse = $this->actingAs($adminA)->getJson('/api/v1/partner/applications');
        $listResponse->assertOk()->assertJsonCount(1, 'data');
        $applicationId = $listResponse->json('data.0.id');

        $this->patchJson("/api/v1/partner/applications/{$applicationId}/status", [
            'status' => 'shortlisted',
            'note' => 'Profile matches the role.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shortlisted');

        $this->actingAs($adminA)
            ->getJson('/api/v1/partner/applications?job_id='.$jobB->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('applications', [
            'user_id' => $applicationA,
            'job_id' => $jobA->id,
            'status' => 'shortlisted',
        ]);
    }
}