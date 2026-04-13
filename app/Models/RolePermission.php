<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = ['role_id', 'database_code', 'schema_name', 'table_name'];

    /**
     * Get full identifier: database.schema.table
     */
    public function getFullIdentifierAttribute(): string
    {
        return "{$this->database_code}.{$this->schema_name}.{$this->table_name}";
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function databaseConnection()
    {
        return $this->belongsTo(DatabaseConnection::class, 'database_code', 'code');
    }
}
