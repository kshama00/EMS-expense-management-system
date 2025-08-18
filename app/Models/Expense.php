<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'location',
        'remarks',
        'amount',
        'approved_amount',
        'meta_data',
        'status',
        'approved_by',
        'approved_at',
        'admin_comment',

    ];

    protected $casts = [
        'meta_data' => 'array',
        'approved_at' => 'datetime',
        'date' => 'date:Y-m-d',

    ];
    public function images()
    {
        return $this->hasMany(ExpenseImage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function typeMap()
    {
        return [
            1 => 'Travel',
            2 => 'Lodging',
            3 => 'Food',
            4 => 'Printing',
            5 => 'Mobile',
            6 => 'Miscellaneous',
        ];
    }

    public static function statusMap()
    {
        return [
            1 => 'Pending',
            2 => 'Approved',
            3 => 'Rejected',
            4 => 'Partially Approved',
            5 => 'Cancelled',
        ];
    }

    public static function subtypeMap()
    {
        return [
            1 => '2_wheeler',
            2 => 'bus',
            3 => 'cab',
            4 => 'train',

        ];
    }


}

