<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name'];

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function auditIssues(): HasMany
    {
        return $this->hasMany(AuditIssue::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function phishingTests(): HasMany
    {
        return $this->hasMany(PhishingTest::class);
    }

    public function kriDisputes(): HasMany
    {
        return $this->hasMany(KriDispute::class);
    }

    public function serverComplianceSnapshots(): HasMany
    {
        return $this->hasMany(ServerComplianceSnapshot::class);
    }
}
