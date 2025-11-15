<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $items = Student::all();
        return response()->json(['data' => $items], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'address' => 'string|max:255',
            'image' => 'string',
        ]);

        $item = Student::create($validated);
        return response()->json(['data' => $item, 'message' => 'Created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $students): JsonResponse
    {
        return response()->json(['data' => $students], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $students): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'address' => 'string|max:255',
            'image' => 'string',
        ]);

        $students->update($validated);
        return response()->json(['data' => $students, 'message' => 'Updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $students): JsonResponse
    {
        $students->delete();
        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
