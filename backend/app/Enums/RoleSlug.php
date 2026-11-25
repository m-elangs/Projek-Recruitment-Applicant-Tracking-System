<?php

namespace App\Enums;

enum RoleSlug: string
{
    case SYSTEM_ADMIN = 'system_admin';
    case RECRUITER = 'recruiter';
    case HIRING_MANAGER = 'hiring_manager';
    case CANDIDATE = 'candidate';

    public function defaultRedirectPath(): string
    {
        return match ($this) {
            self::CANDIDATE => '/portal/dashboard',
            default => '/dashboard',
        };
    }

    public function isInternal(): bool
    {
        return $this !== self::CANDIDATE;
    }
}
