<?php

namespace Database\Seeders;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = [
            ['name' => 'University of Lagos', 'abbreviation' => 'UNILAG', 'type' => InstitutionType::FederalUniversity, 'state' => 'Lagos'],
            ['name' => 'Lagos State University', 'abbreviation' => 'LASU', 'type' => InstitutionType::StateUniversity, 'state' => 'Lagos'],
            ['name' => 'Yaba College of Technology', 'abbreviation' => 'YABATECH', 'type' => InstitutionType::Polytechnic, 'state' => 'Lagos'],
            ['name' => 'University of Ibadan', 'abbreviation' => 'UI', 'type' => InstitutionType::FederalUniversity, 'state' => 'Oyo'],
            ['name' => 'Covenant University', 'abbreviation' => 'CU', 'type' => InstitutionType::PrivateUniversity, 'state' => 'Ogun'],
        ];

        foreach ($institutions as $institution) {
            Institution::query()->updateOrCreate(
                ['abbreviation' => $institution['abbreviation']],
                [...$institution, 'is_active' => true],
            );
        }
    }
}
