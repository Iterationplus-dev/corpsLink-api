<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleSeeder extends Seeder
{
    /**
     * Institutions with the full Nigerian list (~650 rows) seeded, only a
     * portion realistically have live transport routes on the platform —
     * the rest of the list exists purely for the registration picker.
     */
    protected const float INSTITUTION_COVERAGE = 0.65;

    protected const int MIN_VEHICLES_PER_INSTITUTION = 2;

    protected const int MAX_VEHICLES_PER_INSTITUTION = 5;

    /**
     * Always given vehicles regardless of the random 65% draw — DatabaseSeeder's
     * demo Test User is tied to UNILAG specifically so it can exercise the
     * booking flow, and these five are the ones a manual tester reaches for.
     *
     * @var array<int, string>
     */
    protected const array GUARANTEED_ABBREVIATIONS = ['UNILAG', 'LASU', 'YABATECH', 'UI', 'CU'];

    /**
     * Capacity must stay a multiple of 4 — Vehicle's seat layout is a fixed
     * 2 | aisle | 2 grid.
     *
     * @var array<int, array{name: string, capacity: int, fares: array<int, int>}>
     */
    protected const array VEHICLE_TYPES = [
        ['name' => 'Toyota Hiace Bus', 'capacity' => 16, 'fares' => [1500, 2000, 2500]],
        ['name' => 'Toyota Coaster Bus', 'capacity' => 32, 'fares' => [1000, 1500, 2000]],
        ['name' => 'Sienna Space Bus', 'capacity' => 8, 'fares' => [2500, 3000, 3500]],
        ['name' => 'Sprinter Bus', 'capacity' => 20, 'fares' => [1500, 2000]],
        ['name' => 'Long Bus', 'capacity' => 44, 'fares' => [800, 1000, 1200]],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $all = Institution::query()->get();

        $guaranteed = $all->whereIn('abbreviation', self::GUARANTEED_ABBREVIATIONS);
        $targetCount = (int) round($all->count() * self::INSTITUTION_COVERAGE);
        $remainder = max(0, $targetCount - $guaranteed->count());

        $selected = $guaranteed->merge(
            $all->diff($guaranteed)->shuffle()->take($remainder),
        );

        $selected->each(function (Institution $institution) {
            $vehicleCount = random_int(self::MIN_VEHICLES_PER_INSTITUTION, self::MAX_VEHICLES_PER_INSTITUTION);

            collect(self::VEHICLE_TYPES)
                ->shuffle()
                ->take($vehicleCount)
                ->values()
                ->each(function (array $spec, int $index) use ($institution) {
                    $name = $spec['name'];

                    $vehicle = Vehicle::factory()->create([
                        'institution_id' => $institution->id,
                        'name' => "{$name} ".chr(65 + $index),
                        'pickup_point' => "{$institution->abbreviation} Main Gate",
                        'destination' => 'Camp',
                        'departure_at' => now()->addDays(random_int(3, 21))->setTime(random_int(6, 9), 0),
                        'capacity' => $spec['capacity'],
                        'fare' => fake()->randomElement($spec['fares']),
                    ]);

                    // A handful of pre-held seats so the seat map isn't empty in dev/demo.
                    // institution_id must be pinned here — UserFactory defaults it to a
                    // fresh Institution::factory() when left unset, which would spawn a
                    // fake institution per placeholder user (bit us once already). email/
                    // phone/call_up_number are pinned too — at this volume, Faker's finite
                    // unique() pools for those can collide (bit us a second time already).
                    // seat->id (a genuine auto-increment PK) is what actually
                    // guarantees no collisions — a hash truncated to a few
                    // digits doesn't, and did collide once already at this
                    // volume (crc32 % 1e8 across ~1,500 users).
                    $vehicle->seats()->inRandomOrder()->take(3)->get()->each(
                        function ($seat) use ($institution) {
                            $unique = str_replace('-', '', (string) Str::uuid());

                            SeatHold::factory()->create([
                                'seat_id' => $seat->id,
                                'user_id' => User::factory([
                                    'institution_id' => $institution->id,
                                    'email' => "seat-hold-{$unique}@placeholder.corpslink.test",
                                    'phone' => '080'.str_pad((string) $seat->id, 8, '0', STR_PAD_LEFT),
                                    'call_up_number' => "NYSC/PH/2026/{$seat->id}",
                                ]),
                            ]);
                        },
                    );
                });
        });
    }
}
