<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $settings = [
            'company_name' => 'TalentFlow Technologies',
            'company_website' => 'https://talentflow.local',
            'default_currency' => 'USD',
            'default_timezone' => 'Asia/Jakarta',
            'offer_approval_required' => false,
            'max_cv_size_mb' => 5,
            'careers_headline' => 'Build high-impact software with our engineering and product teams.',
        ];

        foreach ($settings as $key => $value) {
            DB::table('company_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => json_encode($value),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
