<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiApiKey extends Model
{
    protected $fillable = [
        'provider_id', 'key_name', 'api_key',
        'last_used_at', 'usage_count', 'token_count', 'added_by'
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'limit_reached'=> 'boolean',
        'last_used_at' => 'datetime',
        'usage_count'  => 'integer',
        'token_count'  => 'integer',
    ];

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_ai_keys', 'api_key_id', 'user_id');
    }

    // Mutator: enkripsi API Key
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    // Accessor: dekripsi API Key
    public function getApiKeyAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Increment usage counter dan update last_used_at tanpa reload model.
     * Dipanggil dari AgenticChatbotController setelah setiap request berhasil.
     */
    public function recordUsage(int $tokens = 0): void
    {
        $this->timestamps = false; // jangan ubah updated_at
        $this->increment('usage_count');
        if ($tokens > 0) {
            $this->increment('token_count', $tokens);
        }
        $this->update(['last_used_at' => now()]);
        $this->timestamps = true;
    }
}
