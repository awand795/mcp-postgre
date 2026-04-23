<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_admin',
        'max_tokens',
        'analysis_scope_limited',
    ];

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role');
    }

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
            'analysis_scope_limited' => 'boolean',
        ];
    }

    /**
     * Get the AI Models accessible to the user.
     */
    public function aiModels()
    {
        return $this->belongsToMany(AiModel::class, 'user_ai_models', 'user_id', 'model_id')
                    ->withPivot('is_enabled')
                    ->withTimestamps();
    }

    public function aiKeys()
    {
        return $this->belongsToMany(AiApiKey::class, 'user_ai_keys', 'user_id', 'api_key_id')
                    ->withPivot('is_enabled')
                    ->withTimestamps();
    }
}
