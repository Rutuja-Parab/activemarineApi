<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TallySync extends Model
{
    use HasFactory;
    protected $table = 'tally_sync';

    protected $fillable = [
        'batch_no',
        'sync_time',
        'request_data',
        'response_data'
    ];
}
