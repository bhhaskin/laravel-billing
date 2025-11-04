<?php

namespace Bhhaskin\Billing\Database\Seeders;

use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PlansSeeder::class,
        ]);
    }
}
