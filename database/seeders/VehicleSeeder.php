<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Institutions with the full Nigerian list (~650 rows) seeded, only a
     * handful realistically have live transport routes on the platform —
     * the rest of the list exists purely for the registration picker.
     *
     * @var array<int, string>
     */
    protected const array DEMO_INSTITUTION_ABBREVIATIONS = ['UNILAG', 'LASU', 'YABATECH', 'UI', 'CU'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleNames = ['Coaster Bus A', 'Coaster Bus B', 'Hiace Shuttle C'];

        Institution::query()
            ->whereIn('abbreviation', self::DEMO_INSTITUTION_ABBREVIATIONS)
            ->get()
            ->each(function (Institution $institution) use ($vehicleNames) {
                foreach ($vehicleNames as $index => $name) {
                    $vehicle = Vehicle::factory()->create([
                        'institution_id' => $institution->id,
                        'name' => $name,
                        'pickup_point' => "{$institution->abbreviation} Main Gate",
                        'destination' => 'Camp',
                        'departure_at' => now()->addDays(5 + $index)->setTime(7, 0),
                        'capacity' => $index === 2 ? 20 : 40,
                    ]);

                    // A handful of pre-held seats so the seat map isn't empty in dev/demo.
                    $vehicle->seats()->inRandomOrder()->take(3)->get()->each(
                        fn ($seat) => SeatHold::factory()->create([
                            'seat_id' => $seat->id,
                            'user_id' => User::factory(),
                        ]),
                    );
                }
            });
    }
}
