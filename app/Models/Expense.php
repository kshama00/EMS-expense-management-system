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
        'date' => 'date',
    ];

    // Relationship: An expense has many images
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

    // Expense.php
    public function childResubmission()
    {
        return $this->hasOne(Expense::class, 'meta_data->previous_expense_id');
    }
    public function typeName()
    {
        $types = [
            1 => 'Travel',
            2 => 'Lodging',
            3 => 'Food',
            4 => 'Printing',
            5 => 'Mobile',
            6 => 'Miscellaneous',
        ];

        return $types[$this->type] ?? 'Unknown';
    }

    public function statusName()
    {
        $statuses = [
            1 => 'Pending',
            2 => 'Approved',
            3 => 'Rejected',
            4 => 'Partially Approved',
            5 => 'Cancelled',
        ];

        return $statuses[$this->status] ?? 'Unknown';
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


}
