<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalController;
use App\Http\Controllers\StudentController;



Route::get('/', function () {
    return view('welcome');
});