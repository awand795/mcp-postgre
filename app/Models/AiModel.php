<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = ['provider_id', 'model_name', 'display_name', 'is_active'];

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_ai_models', 'model_id', 'user_id');
    }
}
