<?php

namespace App\Models;
use Laravel\Sanctum\HasApiTokens;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasPushSubscriptions;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'status',
        'image',
        'google_id',
        'is_verified',
        'verification_code',
        'verification_code_expire',
        'email_verified_at',
        'image_file_id',
        'created_by',
        'updated_by',
        'facebook_id',
    ];
    // protected $appends = ['firstName', 'lastName'];
    protected $casts = [
        'verification_code_expire' => 'datetime:d-m-Y g:i:s A',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
        'email_verified_at' => 'datetime:d-m-Y g:i:s A',
        'created_by' => 'array',
        'updated_by' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'is_verified',
        'verification_code',
        'verification_code_expire',
        'email_verified_at',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime:d-m-Y g:i:s A',
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // public function updater()
    // {
    //     return $this->belongsTo(User::class, 'updated_by');
    // }
}
