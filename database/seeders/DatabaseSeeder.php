<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events are left enabled (no WithoutModelEvents) — VehicleSeeder
     * relies on Vehicle's `created` event to auto-generate seats.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            InstitutionSeeder::class,
            VehicleSeeder::class,
            FaqSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            // UNILAG specifically — it's one of the handful of institutions
            // VehicleSeeder gives demo vehicles to, so this user can
            // actually exercise the booking flow against seeded data.
            'institution_id' => Institution::query()->where('abbreviation', 'UNILAG')->first()?->id,
        ]);

        $user->assignRole('corps_member');
    }
}
