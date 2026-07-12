<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Source of truth is data/institutions.json — a curated list of Nigerian
     * federal/state/private universities, polytechnics, and colleges of
     * education. This does a full replace: rows no longer present in the
     * dataset are removed (vehicles.institution_id cascades on delete,
     * users.institution_id/pending_registrations.institution_id null out
     * instead of deleting the user/registration).
     */
    public function run(): void
    {
        $institutions = json_decode(
            File::get(database_path('seeders/data/institutions.json')),
            true,
        );

        $abbreviations = array_column($institutions, 'abbreviation');

        Institution::query()->whereNotIn('abbreviation', $abbreviations)->delete();

        foreach ($institutions as $institution) {
            Institution::query()->updateOrCreate(
                ['abbreviation' => $institution['abbreviation']],
                [...$institution, 'is_active' => true],
            );
        }
    }
}
