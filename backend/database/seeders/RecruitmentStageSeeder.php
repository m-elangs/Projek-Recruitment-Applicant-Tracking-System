<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecruitmentStageSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $stages = [
            ['slug' => 'applied', 'name' => 'Applied', 'display_order' => 1, 'is_terminal' => false],
            ['slug' => 'screening', 'name' => 'Screening', 'display_order' => 2, 'is_terminal' => false],
            ['slug' => 'shortlisted', 'name' => 'Shortlisted', 'display_order' => 3, 'is_terminal' => false],
            ['slug' => 'interview', 'name' => 'Interview', 'display_order' => 4, 'is_terminal' => false],
            ['slug' => 'assessment', 'name' => 'Assessment', 'display_order' => 5, 'is_terminal' => false],
            ['slug' => 'offered', 'name' => 'Offered', 'display_order' => 6, 'is_terminal' => false],
            ['slug' => 'hired', 'name' => 'Hired', 'display_order' => 7, 'is_terminal' => true],
            ['slug' => 'rejected', 'name' => 'Rejected', 'display_order' => 8, 'is_terminal' => true],
            ['slug' => 'withdrawn', 'name' => 'Withdrawn', 'display_order' => 9, 'is_terminal' => true],
        ];

        foreach ($stages as $stage) {
            DB::table('recruitment_stages')->updateOrInsert(
                ['slug' => $stage['slug']],
                array_merge($stage, ['updated_at' => $now, 'created_at' => $now])
            );
        }
    }
}
