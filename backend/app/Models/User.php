<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role, department and is_demo are deliberately not mass assignable: they decide what a user may do.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_demo' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    /** Unit owners may only act on their own department. */
    public function ownsDepartment(int $departmentId): bool
    {
        return $this->hasRole(Role::UnitOwner) && $this->department_id === $departmentId;
    }

    /** Public profile for the API (never includes credentials). */
    public function toProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'has_work_queue' => $this->role->hasWorkQueue(),
            'department' => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null,
        ];
    }
}
