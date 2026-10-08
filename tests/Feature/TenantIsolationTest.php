<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
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
}