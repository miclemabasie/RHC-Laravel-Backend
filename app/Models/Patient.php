<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Patient extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'dob'
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}