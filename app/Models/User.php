<?php

namespace App\Models;
use Laravel\Sanctum\HasApiTokens;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
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
        'role ',
        'image',
        'google_id',
        'is_verified',
        'verification_code',
        'verification_code_expire',
        'email_verified_at',
        'image_file_id'

    ];
    protected $appends = ['firstName', 'lastName'];
    protected $casts = [
        'verification_code_expire' => 'datetime',
    ];
    public function getFirstNameAttribute()
    {
        return $this->attributes['first_name'];
    }

    public function getLastNameAttribute()
    {
        return $this->attributes['last_name'];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'first_name',
        'last_name',
        'google_id',
        'is_verified',
        'verification_code',
        'verification_code_expire',
        'email_verified_at',
        'create_at',
        'update_at'
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
}
