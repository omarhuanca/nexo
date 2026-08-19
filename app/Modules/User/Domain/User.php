<?php

namespace App\Modules\User\Domain;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'scopes',
        'active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'scopes' => 'array',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'scopes' => $this->scopes ?? [],
        ];
    }

    public function hasScope(string $requiredScope): bool
    {
        $scopes = $this->scopes ?? [];

        if (in_array('*:*', $scopes, true)) {
            return true;
        }

        [$module, $permission] = array_pad(explode(':', $requiredScope, 2), 2, 'read');

        if (in_array("{$module}:{$permission}", $scopes, true)) {
            return true;
        }

        if (in_array("{$module}:*", $scopes, true)) {
            return true;
        }

        return false;
    }
}