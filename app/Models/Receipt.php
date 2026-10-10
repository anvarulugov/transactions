<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = ['user_id', 'image_path', 'image_hash', 'ocr_text', 'draft', 'confirmed_items', 'confirmed_at'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'confirmed_items' => 'array', 'confirmed_at' => 'datetime'];
    }
}
