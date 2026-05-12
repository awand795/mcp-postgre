<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTableFilter extends Model
{
    protected $fillable = [
        'user_id',
        'database_connection_id',
        'table_name',
        'filter_condition'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function databaseConnection()
    {
        return $this->belongsTo(DatabaseConnection::class, 'database_connection_id');
    }
}
