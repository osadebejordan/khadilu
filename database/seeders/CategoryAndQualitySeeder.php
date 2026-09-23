<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryAndQualitySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Smartphone', 'Tablet', 'Laptop', 'Accessory', 'Charger', 'Screen', 'Battery', 'Backglass', 'Housing', 'Other'];
        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(['name' => $cat], ['updated_at' => now(), 'created_at' => now()]);
        }

        $qualities = ['Original', 'Refurbished', 'Used', 'Generic', 'Standard'];
        foreach ($qualities as $qual) {
            DB::table('qualities')->updateOrInsert(['name' => $qual], ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
