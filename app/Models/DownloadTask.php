<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadTask extends Model
{
    protected $fillable = [
        'url',
        'format',
        'title',
        'status',
        'file_url',
        'error_message',
    ];
}
