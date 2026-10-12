<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            [
                'name' => 'SBI Demo',
                'slug' => 'sbi-demo',
                'industry' => 'Banking',
            ],
            [
                'name' => 'Acme Corp',
                'slug' => 'acme-corp',
                'industry' => 'Technology',
            ],
        ];

        foreach ($partners as $partner) {
            Partner::query()->firstOrCreate(
                ['slug' => $partner['slug']],
                [
                    'name' => $partner['name'],
                    'industry' => $partner['industry'],
                    'status' => 'active',
                    'max_active_jobs' => 10,
                ]
            );
        }
    }
}