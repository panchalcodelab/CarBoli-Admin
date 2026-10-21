<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'mobile', 'status', 'last_login_at', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const array STAFF_STATUSES = ['active', 'inactive', 'suspended'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The admin roles permitted to access the custom admin panel.
     *
     * @var list<string>
     */
    public const ADMIN_ROLES = [
        'Super Admin',
        'Auction Manager',
        'Car / Inventory Manager',
        'Inspection Staff',
        'Finance Manager',
        'Customer Support',
    ];

    /**
     * Whether this user may access the custom admin panel.
     *
     * Access requires an "active" status plus either a seeded admin role
     * (preserves the original behavior for built-in roles) or any role that
     * grants at least one permission. The latter lets roles created through
     * Role Management reach the panel, while users with no role — or a role
     * carrying no permissions — remain locked out.
     */
    public function canAccessAdminPanel(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->hasAnyRole(self::ADMIN_ROLES)
            || $this->getAllPermissions()->isNotEmpty();
    }
}
