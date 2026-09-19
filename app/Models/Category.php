<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'status',
        'featured',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'status' => 'boolean',
    ];

    public function productCategory()
    {
        return $this->hasMany(ProductCategory::class, 'category_id', 'id');
    }
}
