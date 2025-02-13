<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TallyInvoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'invoice_no',
        'status',
        'sync_time',
        'remarks',
        'date'
    ];
}
