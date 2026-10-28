<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\CitizenProfile;
use App\Models\ComplaintReport;
use App\Models\ComplaintWorkUnit;
use App\Models\EmployeeProfile;
use App\Models\UserDevice;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,hasApiTokens,HasRoles;



    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'otp',
        'phone_number',
        'must_change_password',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    //--------------- RelationShip ---------------

    public function citizenProfile()
    {
        return $this->hasOne(CitizenProfile::class);
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function changedComplaintStatuses(): HasMany
    {
        return $this->hasMany(
            ComplaintStatusHistory::class,
            'changed_by'
        );
    }

    public function linkedComplaintReports(): HasMany
    {
        return $this->hasMany(
            ComplaintReport::class,
            'linked_by'
        );
    }

    public function assignedComplaintWorkUnits(): HasMany
    {
        return $this->hasMany(
            ComplaintWorkUnit::class,
            'assigned_by'
        );
    }

    public function managedWorkUnits(): HasMany
    {
        return $this->hasMany(WorkUnit::class, 'department_manager_id');
    }

}
