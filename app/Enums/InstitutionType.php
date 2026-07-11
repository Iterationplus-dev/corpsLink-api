<?php

namespace App\Enums;

enum InstitutionType: string
{
    case FederalUniversity = 'federal_university';
    case StateUniversity = 'state_university';
    case PrivateUniversity = 'private_university';
    case Polytechnic = 'polytechnic';
    case CollegeOfEducation = 'college_of_education';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FederalUniversity => 'Federal University',
            self::StateUniversity => 'State University',
            self::PrivateUniversity => 'Private University',
            self::Polytechnic => 'Polytechnic',
            self::CollegeOfEducation => 'College of Education',
            self::Other => 'Other',
        };
    }
}
