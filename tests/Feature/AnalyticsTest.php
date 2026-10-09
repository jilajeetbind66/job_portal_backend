<?php
namespace Tests\Feature;

use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole('super_admin');

        return $user;
    }

    private function createPartner(
        string $name,
        string $slug,
        string $status = 'active'
    ): Partner {
        return Partner::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'max_active_jobs' => 10,
        ]);
    }

    private function createJob(
        Partner $partner,
        User $admin,
        string $title,
        string $slug,
        string $status
    ): Job {
        return Job::create([
            'partner_id' => $partner->id,
            'posted_by' => $admin->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Analytics test job description.',
            'employment_type' => 'full_time',
            'work_mode' => 'onsite',
            'location' => 'Noida',
            'min_exp' => 1,
            'max_exp' => 5,
            'salary_min' => 30000,
            'salary_max' => 60000,
            'openings' => 1,
            'status' => $status,
        ]);
    }

    public function test_super_admin_can_view_analytics(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/analytics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'partners' => [
                        'total',
                        'by_status' => [
                            'active',
                            'suspended',
                        ],
                    ],
                    'jobs' => [
                        'total',
                        'by_status' => [
                            'draft',
                            'pending_review',
                            'published',
                            'closed',
                            'rejected',
                        ],
                    ],
                    'signups_per_day',
                ],
            ]);
    }

    public function test_non_super_admin_cannot_view_analytics(): void
    {
        $candidate = User::factory()->create([
            'status' => 'active',
            'partner_id' => null,
        ]);

        $candidate->assignRole('candidate');

        $response = $this->actingAs($candidate)
            ->getJson('/api/v1/admin/analytics');

        $response->assertForbidden();
    }

    public function test_guest_cannot_view_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics');

        $response->assertUnauthorized();
    }

    public function test_analytics_returns_correct_partner_and_job_counts(): void
    {
        $admin = $this->createSuperAdmin();

        $this->createPartner(
            'Analytics Active Partner',
            'analytics-active-partner',
            'active'
        );

        $this->createPartner(
            'Analytics Suspended Partner',
            'analytics-suspended-partner',
            'suspended'
        );

        $jobPartner = $this->createPartner(
            'Analytics Job Partner',
            'analytics-job-partner'
        );

        $this->createJob(
            $jobPartner,
            $admin,
            'Analytics Draft Job',
            'analytics-draft-job',
            'draft'
        );

        $this->createJob(
            $jobPartner,
            $admin,
            'Analytics Published Job',
            'analytics-published-job',
            'published'
        );

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/analytics');

        $response
            ->assertOk()
            ->assertJsonPath('data.partners.total', 3)
            ->assertJsonPath('data.partners.by_status.active', 2)
            ->assertJsonPath('data.partners.by_status.suspended', 1)
            ->assertJsonPath('data.jobs.total', 2)
            ->assertJsonPath('data.jobs.by_status.draft', 1)
            ->assertJsonPath('data.jobs.by_status.published', 1)
            ->assertJsonPath('data.jobs.by_status.closed', 0)
            ->assertJsonPath('data.jobs.by_status.rejected', 0)
            ->assertJsonPath('data.jobs.by_status.pending_review', 0);
    }

    public function test_analytics_returns_seven_days_of_signup_data(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/analytics');

        $response
            ->assertOk()
            ->assertJsonCount(7, 'data.signups_per_day')
            ->assertJsonPath(
                'data.signups_per_day.6.date',
                now()->toDateString()
            );

        $signups = $response->json('data.signups_per_day');

        $this->assertSame(
            now()->subDays(6)->toDateString(),
            $signups[0]['date']
        );

        foreach ($signups as $signup) {
            $this->assertArrayHasKey('date', $signup);
            $this->assertArrayHasKey('total', $signup);
            $this->assertIsInt($signup['total']);
        }
    }
}
