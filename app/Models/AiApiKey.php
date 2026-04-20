<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiApiKey extends Model
{
    protected $fillable = ['provider_id', 'key_name', 'api_key', 'is_active', 'limit_reached', 'last_used_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'limit_reached' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_ai_keys', 'api_key_id', 'user_id');
    }

    // Mutator to encrypt the API Key
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    // Accessor to decrypt the API Key
    public function getApiKeyAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
