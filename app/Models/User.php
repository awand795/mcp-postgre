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
        'description',
        'email',
        'password',
        'role',
        'is_admin',
        'is_super_admin',
        'added_by',
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
            'is_super_admin' => 'boolean',
            'analysis_scope_limited' => 'boolean',
        ];
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
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

    public function tableFilters()
    {
        return $this->hasMany(UserTableFilter::class);
    }
}
