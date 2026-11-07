<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected string $table = 'users';

    protected array $fillable = [
        'institutional_id',
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'is_active',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
    ];

    protected array $casts = [
        'is_active' => 'boolean',
        'department_id' => 'integer',
    ];

    public function roleEnum(): UserRole
    {
        $roleValue = is_string($this->role) ? $this->role : (string) ($this->attributes['role'] ?? 'student');
        return UserRole::from($roleValue);
    }

    public function hasRole(string|UserRole ...$roles): bool
    {
        $current = is_string($this->role) ? $this->role : (string) ($this->attributes['role'] ?? '');
        foreach ($roles as $role) {
            $target = $role instanceof UserRole ? $role->value : $role;
            if ($current === $target) {
                return true;
            }
        }
        return false;
    }
}
