<?php

namespace App\Enums;

enum EmployeeDocumentType: string
{
    case Contract = 'contract';
    case NationalId = 'national_id';
    case Certificate = 'certificate';
    case Cv = 'cv';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Employment contract',
            self::NationalId => 'National ID',
            self::Certificate => 'Academic certificate',
            self::Cv => 'CV / résumé',
            self::Other => 'Other',
        };
    }
}
