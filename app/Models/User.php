<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'microsoft_id',
        'microsoft_token',
        'microsoft_refresh_token',
        'microsoft_token_expires_at',
        'name',
        'email',
        'password',
        'card_number',
        'telegram_id',
        'phone',
        'profile_url',
        'signature_url',
        'building_id',
        'default_department_id',
        'default_campus_id',
        'current_position_id',
        'default_warehouse_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }
    public function defaultDepartment()
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    public function campus()
    {
        return $this->belongsToMany(Campus::class, 'campus_user')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    public function defaultCampus()
    {
        return $this->belongsTo(Campus::class, 'default_campus_id');
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_user')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    public function defaultWarehouse()
    {
        return $this->warehouses()->wherePivot('is_default', true)->first();
    }

    public function hasWarehouseAccess(int $warehouseId): bool
    {
        return $this->warehouses()->where('warehouses.id', $warehouseId)->exists();
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'position_user')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }
    

    public function defaultPosition()
    {
        return $this->belongsTo(Position::class, 'current_position_id');
    }
}
