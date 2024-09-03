<?php

namespace App\Laravue\Models;

use App\ActivityLog;
use App\Customer;
use App\Models\Warehouse\Warehouse;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class User
 *
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role[] $roles
 *
 * @method static User create(array $user)
 * @package App
 */
class User extends Authenticatable
{
    use Notifiable, HasRoles, HasApiTokens;
    use SoftDeletes;

    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'id');
    }
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class);
    }
    public function logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'user_type'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Set permissions guard to API by default
     * @var string
     */
    protected $guard_name = 'api';

    /**
     * @inheritdoc
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * @inheritdoc
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Check if user has a permission
     * @param String
     * @return bool
     */
    public function hasPermission($permission)
    {
        foreach ($this->roles as $role) {
            if (in_array($permission, $role->permissions->toArray())) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {

        return $this->hasRole('admin');
        // foreach ($this->roles as $role) {
        //     if ($role->isAdmin()) {
        //         return true;
        //     }
        // }

        // return false;
    }
    public function isAssistantAdmin(): bool
    {
        return $this->hasRole('assistant admin');
        // foreach ($this->roles as $role) {
        //     if ($role->isAssistantAdmin()) {
        //         return true;
        //     }
        // }

        // return false;
    }
    // public function receivesBroadcastNotificationsOn()
    // {
    //     return 'users.' . $this->id;
    // }

}
