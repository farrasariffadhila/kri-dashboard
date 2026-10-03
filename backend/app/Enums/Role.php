<?php

namespace App\Enums;

enum Role: string
{
    case Director = 'director';
    case UnitOwner = 'unit_owner';
    case Auditor = 'auditor';
    case RiskOfficer = 'risk_officer';

    /** Display label in the request's locale (lang/{id,en}/roles.php). */
    public function label(): string
    {
        return __("roles.{$this->value}");
    }

    /** Roles that take part in the remediation workflow and therefore have a work queue. */
    public function hasWorkQueue(): bool
    {
        return in_array($this, [self::UnitOwner, self::Auditor], true);
    }
}
