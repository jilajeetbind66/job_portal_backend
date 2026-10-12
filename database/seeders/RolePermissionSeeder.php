<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Candidate
            'jobs.view',
            'jobs.submit-review',
            'jobs.apply',
            'jobs.save',
            'applications.view-own',
            'applications.withdraw',
            'profile.view-own',
            'profile.update-own',
            'resume.upload-own',

            // Partner
            'jobs.create',
            'jobs.update',
            'jobs.delete',
            'jobs.close',
            'jobs.view-own',
            'applications.view',
            'applications.update-status',
            'candidates.view',
            'recruiters.view',
            'recruiters.create',
            'recruiters.update',

            // Partner Admin
            'partner.view',
            'partner.update',
            'partner.users.manage',
            'partner.jobs.moderate',
            'partner.analytics.view',

            // Super Admin
            'partners.view',
            'partners.create',
            'partners.update',
            'partners.suspend',
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.delete',
            'jobs.moderate',
            'audit-logs.view',
            'analytics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $candidate = Role::findOrCreate('candidate', 'web');
        $partnerRecruiter = Role::findOrCreate('partner_recruiter', 'web');
        $partnerAdmin = Role::findOrCreate('partner_admin', 'web');
        $superAdmin = Role::findOrCreate('super_admin', 'web');

        $candidate->syncPermissions([
            'jobs.view',
            'jobs.apply',
            'jobs.save',
            'applications.view-own',
            'applications.withdraw',
            'profile.view-own',
            'profile.update-own',
            'resume.upload-own',
        ]);

        $partnerRecruiter->syncPermissions([
            'jobs.view',
            'jobs.create',
            'jobs.update',
            'jobs.delete',
            'jobs.close',
            'jobs.view-own',
            'jobs.submit-review',
            'applications.view',
            'applications.update-status',
            'candidates.view',
        ]);

        $partnerAdmin->syncPermissions([
            'jobs.view',
            'jobs.create',
            'jobs.update',
            'jobs.delete',
            'jobs.close',
            'jobs.view-own',
            'jobs.submit-review',
            'applications.view',
            'applications.update-status',
            'candidates.view',
            'recruiters.view',
            'recruiters.create',
            'recruiters.update',
            'partner.view',
            'partner.update',
            'partner.users.manage',
            'partner.jobs.moderate',
            'partner.analytics.view',
        ]);

        $superAdmin->syncPermissions(Permission::all());
    }
}
