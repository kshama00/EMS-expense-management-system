<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    protected $table = 'headquarters';

    protected $fillable = [
        'fin_year',
        'hq_name',
        'hq_code',
        'territory_code',
        'territory_name',
        'region_code',
        'region_name',
        'zone_code',
        'zone_name',
        'division_code',
        'division_name',
        'sbu_code',
        'sbu_name',
        'department_code',
        'department_name',
        'type',
        'brand',
        'status',
        'state',
        'lang',
        'created_by',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'hq_code', 'hq_code');
    }



}
