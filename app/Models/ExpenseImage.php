<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseImage extends Model
{
    protected $fillable = ['expense_id', 'path'];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
