<?php

namespace Sirval\LaravelSmartMigrations\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';
    
    protected $fillable = [
        'title',
        'content',
    ];
}
