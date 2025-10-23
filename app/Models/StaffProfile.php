<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StaffProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'start_date',
        'job_title',
        'department_unit',
        "profile_photo"
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProfilePhotoUrlAttribute()
    {
        if (!$this->profile_photo) {
            return null;
        }

        // If it already contains a full URL or storage path, return as is
        if (strpos($this->profile_photo, 'http') === 0 || strpos($this->profile_photo, 'storage/') === 0) {
            return $this->profile_photo;
        }

        // Otherwise, assume it's stored in public disk
        return Storage::disk('public')->url($this->profile_photo);
    }

    /**
     * Get the attributes that should be appended to the model's array form.
     */
    protected $appends = ['profile_photo_url'];
}