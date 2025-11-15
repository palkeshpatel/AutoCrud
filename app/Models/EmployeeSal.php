<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeSal extends Model
{
    use HasFactory;

    protected $table = 'employee_sal';

    protected $fillable = [
        'salary',
        'employee_id',
    ];

    /**
     * Get the Employee that owns this EmployeeSal.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}