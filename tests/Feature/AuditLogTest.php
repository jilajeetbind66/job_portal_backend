<?php
namespace Tests\Feature;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\User;
use App\Models\Job;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    private function createPartner(string $name, string $slug): Partner
    {
        return Partner::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
    }

    public function test_user_creation_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Audit Candidate',
                'email' => 'audit-candidate@example.com',
                'phone' => '9876543210',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'role' => 'candidate',
            ]);

        $response->assertCreated();

        $user = User::where(
            'email',
            'audit-candidate@example.com'
        )->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'partner_id' => null,
            'action' => 'user.created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_user_update_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'audit-update@example.com',
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => 'audit-updated@example.com',
                'phone' => '9999999999',
                'role' => 'candidate',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'partner_id' => null,
            'action' => 'user.updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_user_status_change_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/status", [
                'status' => 'suspended',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $log = AuditLog::query()
            ->where('actor_id', $admin->id)
            ->where('action', 'user.status_changed')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            'active',
            $log->meta['old_status']
        );

        $this->assertSame(
            'suspended',
            $log->meta['new_status']
        );
    }

    public function test_partner_user_creation_records_partner_id(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Audit Partner',
            'audit-partner'
        );

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Partner Recruiter',
                'email' => 'audit-recruiter@example.com',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'partner_id' => $partner->id,
                'role' => 'partner_recruiter',
            ]);

        $response->assertCreated();

        $user = User::where(
            'email',
            'audit-recruiter@example.com'
        )->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'partner_id' => $partner->id,
            'action' => 'user.created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }


    
    public function test_partner_creation_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/partners', [
                'name' => 'Audit Created Partner',
                'slug' => 'audit-created-partner',
                'industry' => 'IT',
                'max_active_jobs' => 25,
            ]);

        $response->assertCreated();

        $partner = Partner::where(
            'slug',
            'audit-created-partner'
        )->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'partner_id' => $partner->id,
            'action' => 'partner.created',
            'subject_type' => Partner::class,
            'subject_id' => $partner->id,
        ]);
    }

    public function test_partner_update_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Audit Old Partner',
            'audit-old-partner'
        );

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/partners/{$partner->id}", [
                'name' => 'Audit Updated Partner',
                'industry' => 'Automotive',
                'max_active_jobs' => 50,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'partner_id' => $partner->id,
            'action' => 'partner.updated',
            'subject_type' => Partner::class,
            'subject_id' => $partner->id,
        ]);
    }

    public function test_partner_status_change_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Audit Status Partner',
            'audit-status-partner'
        );

        $response = $this->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/partners/{$partner->id}/status",
                ['status' => 'suspended']
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $log = AuditLog::query()
            ->where('actor_id', $admin->id)
            ->where('action', 'partner.status_changed')
            ->where('subject_type', Partner::class)
            ->where('subject_id', $partner->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('active', $log->meta['old_status']);
        $this->assertSame('suspended', $log->meta['new_status']);
    }

    public function test_job_moderation_creates_audit_log(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Audit Job Partner',
            'audit-job-partner'
        );

        $recruiter = User::factory()->create([
            'status' => 'active',
            'partner_id' => $partner->id,
        ]);

        $recruiter->assignRole('partner_admin');

        $job = Job::create([
            'partner_id' => $partner->id,
            'posted_by' => $recruiter->id,
            'title' => 'Audit Laravel Developer',
            'slug' => 'audit-laravel-developer',
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
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(
                "/api/v1/admin/jobs/{$job->id}/moderate",
                [
                    'status' => 'published',
                    'note' => 'Approved for publication.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $log = AuditLog::query()
            ->where('actor_id', $admin->id)
            ->where('action', 'job.moderated')
            ->where('subject_type', Job::class)
            ->where('subject_id', $job->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('pending_review', $log->meta['old_status']);
        $this->assertSame('published', $log->meta['new_status']);
        $this->assertSame($partner->id, $log->partner_id);
    }

    public function test_super_admin_can_view_audit_logs(): void
    {
        $admin = $this->createSuperAdmin();

        AuditLog::create([
            'actor_id' => $admin->id,
            'partner_id' => null,
            'action' => 'user.created',
            'subject_type' => null,
            'subject_id' => null,
            'meta' => ['name' => 'Test Candidate'],
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/audit-logs');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.action', 'user.created')
            ->assertJsonPath('current_page', 1);
    }

    public function test_non_super_admin_cannot_view_audit_logs(): void
    {
        $candidate = User::factory()->create([
            'status' => 'active',
            'partner_id' => null,
        ]);

        $candidate->assignRole('candidate');

        $response = $this->actingAs($candidate)
            ->getJson('/api/v1/admin/audit-logs');

        $response->assertForbidden();
    }

    public function test_audit_logs_can_be_filtered_and_paginated(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Audit Filter Partner',
            'audit-filter-partner'
        );

        foreach (['partner.created', 'partner.created'] as $action) {
            AuditLog::create([
                'actor_id' => $admin->id,
                'partner_id' => $partner->id,
                'action' => $action,
                'subject_type' => Partner::class,
                'subject_id' => $partner->id,
                'meta' => ['name' => $partner->name],
                'ip' => '127.0.0.1',
                'created_at' => now(),
            ]);
        }

        AuditLog::create([
            'actor_id' => $admin->id,
            'partner_id' => $partner->id,
            'action' => 'partner.updated',
            'subject_type' => Partner::class,
            'subject_id' => $partner->id,
            'meta' => ['name' => $partner->name],
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/admin/audit-logs'
            . '?action=partner.created'
            . '&actor_id=' . $admin->id
            . '&partner_id=' . $partner->id
            . '&per_page=1'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.action', 'partner.created')
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 2);
    }

    public function test_unauthenticated_user_cannot_view_audit_logs(): void
    {
        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertUnauthorized();
    }


public function test_audit_log_filters_reject_invalid_pagination(): void
{
    $admin = $this->createSuperAdmin();

    $response = $this->actingAs($admin)->getJson(
        '/api/v1/admin/audit-logs?per_page=101'
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
}


public function test_audit_log_filters_reject_invalid_date_range(): void
{
    $admin = $this->createSuperAdmin();

    $response = $this->actingAs($admin)->getJson(
        '/api/v1/admin/audit-logs?from=2026-10-10&to=2026-10-01'
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
}


public function test_audit_log_filters_reject_invalid_ids(): void
{
    $admin = $this->createSuperAdmin();

    $response = $this->actingAs($admin)->getJson(
        '/api/v1/admin/audit-logs?actor_id=0&partner_id=-1'
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'actor_id',
            'partner_id',
        ]);
}

    }