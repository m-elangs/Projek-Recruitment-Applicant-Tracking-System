<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            RecruitmentStageSeeder::class,
            CompanySettingSeeder::class,
            DemoUserSeeder::class,
            DemoRecruitmentSeeder::class,
        ]);
    }
}
