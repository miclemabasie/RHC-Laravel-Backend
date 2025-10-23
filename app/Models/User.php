<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids; // ✅ Add HasUuids

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are strings

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function mfaCodes()
    {
        return $this->hasMany(MFACode::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
