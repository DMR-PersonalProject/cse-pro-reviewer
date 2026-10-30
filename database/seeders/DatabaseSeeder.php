<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Model events stay on: questions normalize their stems
     * and check for duplicates when saved.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            TaxonomySeeder::class,
            SampleContentSeeder::class,
        ]);
    }
}
