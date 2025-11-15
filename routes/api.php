<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;


Route::apiResource('students', StudentController::class);
