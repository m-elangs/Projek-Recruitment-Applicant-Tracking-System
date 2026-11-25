<?php

namespace App\Enums;

enum StageSlug: string
{
    case APPLIED = 'applied';
    case SCREENING = 'screening';
    case SHORTLISTED = 'shortlisted';
    case INTERVIEW = 'interview';
    case ASSESSMENT = 'assessment';
    case OFFERED = 'offered';
    case HIRED = 'hired';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [self::HIRED, self::REJECTED, self::WITHDRAWN], true);
    }
}
