<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeDoubleAuth extends Model
{
    protected $table = 'codes_double_auth';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'envoye_le' => 'datetime'];
    }
}
