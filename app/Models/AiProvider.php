<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = ['name', 'code', 'base_url', 'is_active'];

    public function models()
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    public function apiKeys()
    {
        return $this->hasMany(AiApiKey::class, 'provider_id');
    }
}
