<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_partner_cannot_see_another_partners_jobs(): void
    {
        // Partner A
        $partnerA = Partner::create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);

        // Partner B
        $partnerB = Partner::create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);

        // Partner A user
        $userA = User::factory()->create([
            'name' => 'Recruiter A',
            'email' => 'recruiter-a@example.com',
            'partner_id' => $partnerA->id,
            'status' => 'active',
        ]);

        // Partner B user
        $userB = User::factory()->create([
            'name' => 'Recruiter B',
            'email' => 'recruiter-b@example.com',
            'partner_id' => $partnerB->id,
            'status' => 'active',
        ]);

        // Login as Partner A
        $this->actingAs($userA);

        // Even if someone tries to pass Partner B's ID,
        // BelongsToPartner should force Partner A's ID.
        $jobA = Job::create([
            'partner_id' => $partnerB->id,
            'posted_by' => $userA->id,
            'title' => 'Laravel Developer - Company A',
            'slug' => 'laravel-developer',
            'description' => 'Laravel developer required.',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'location' => 'Kanpur',
            'min_exp' => 1,
            'max_exp' => 3,
            'salary_min' => 30000,
            'salary_max' => 50000,
            'openings' => 2,
            'status' => 'published',
        ]);

        // Confirm partner_id was automatically assigned to Partner A.
        $this->assertEquals(
            $partnerA->id,
            $jobA->partner_id
        );

        // Login as Partner B
        $this->actingAs($userB);

        $jobB = Job::create([
            'partner_id' => $partnerA->id,
            'posted_by' => $userB->id,
            'title' => 'PHP Developer - Company B',
            'slug' => 'php-developer',
            'description' => 'PHP developer required.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Delhi',
            'min_exp' => 2,
            'max_exp' => 5,
            'salary_min' => 40000,
            'salary_max' => 70000,
            'openings' => 1,
            'status' => 'published',
        ]);

        // Confirm partner_id was automatically assigned to Partner B.
        $this->assertEquals(
            $partnerB->id,
            $jobB->partner_id
        );

        // ---------------------------------------------
        // Partner A should only see Partner A's job
        // ---------------------------------------------

        $this->actingAs($userA);

        $jobsForA = Job::query()->get();

        $this->assertTrue(
            $jobsForA->contains('id', $jobA->id)
        );

        $this->assertFalse(
            $jobsForA->contains('id', $jobB->id)
        );

        // ---------------------------------------------
        // Partner B should only see Partner B's job
        // ---------------------------------------------

        $this->actingAs($userB);

        $jobsForB = Job::query()->get();

        $this->assertTrue(
            $jobsForB->contains('id', $jobB->id)
        );

        $this->assertFalse(
            $jobsForB->contains('id', $jobA->id)
        );
    }

    public function test_demo_seeder_creates_two_partners_idempotently(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(
            2,
            Partner::query()
                ->whereIn('slug', ['sbi-demo', 'acme-corp'])
                ->count()
        );
    }

    public function test_public_job_and_company_endpoints_only_return_visible_published_jobs(): void
    {
        $partner = Partner::create([
            'name' => 'Public Company',
            'slug' => 'public-company',
            'industry' => 'Technology',
            'about' => 'A public company profile.',
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);

        $publishedJob = Job::create([
            'partner_id' => $partner->id,
            'title' => 'Public Laravel Developer',
            'slug' => 'public-laravel-developer',
            'description' => 'Build public job portal features.',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Remote',
            'min_exp' => 2,
            'max_exp' => 4,
            'salary_min' => 50000,
            'salary_max' => 80000,
            'openings' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Job::create([
            'partner_id' => $partner->id,
            'title' => 'Draft Internal Role',
            'slug' => 'draft-internal-role',
            'description' => 'This job must remain private.',
            'employment_type' => 'full_time',
            'work_mode' => 'onsite',
            'location' => 'Delhi',
            'openings' => 1,
            'status' => 'draft',
        ]);

        Job::create([
            'partner_id' => $partner->id,
            'title' => 'Expired Role',
            'slug' => 'expired-role',
            'description' => 'This expired job must not be listed.',
            'employment_type' => 'full_time',
            'work_mode' => 'hybrid',
            'location' => 'Noida',
            'openings' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/public/jobs?perPage=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publishedJob->id)
            ->assertJsonPath('data.0.company_name', 'Public Company')
            ->assertJsonPath('data.0.workplace_type', 'remote');

        $this->getJson('/api/v1/public/jobs/public-laravel-developer')
            ->assertOk()
            ->assertJsonPath('data.id', $publishedJob->id)
            ->assertJsonPath('data.company_slug', 'public-company');

        $this->getJson('/api/v1/public/companies/public-company')
            ->assertOk()
            ->assertJsonPath('data.name', 'Public Company')
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonPath('data.jobs.0.id', $publishedJob->id);

        $this->getJson('/api/v1/public/jobs/draft-internal-role')
            ->assertNotFound();
    }
}