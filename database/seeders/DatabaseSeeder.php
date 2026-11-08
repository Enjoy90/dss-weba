<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            DemoMasterSeeder::class,
            DemoOrderSeeder::class,
            CriteriaSeeder::class,
            DemoAssessmentSeeder::class,
        ]);
    }
}
