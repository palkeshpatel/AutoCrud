<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employees = Employee::latest()->paginate(10);
        return view('employees.index', compact('employees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employees.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'string',
            'detail' => 'string',
            'image' => 'image|max:2048',
            'image2' => 'image|max:2048',
        ]);

        // Handle file upload for image
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('employees', 'public');
        }

        // Handle file upload for image2
        if ($request->hasFile('image2')) {
            $validated['image2'] = $request->file('image2')->store('employees', 'public');
        }

        Employee::create($validated);
        return redirect()->route('employees.index')->with('success', 'Created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        return view('employees.show', compact('employee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        return view('employees.edit', compact('employee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'string',
            'detail' => 'string',
            'image' => 'image|max:2048',
            'image2' => 'image|max:2048',
        ]);

        // Handle file upload for image
        if ($request->hasFile('image')) {
            // Delete old file if exists
            if ($employee->image) {
                \Storage::disk('public')->delete($employee->image);
            }
            $validated['image'] = $request->file('image')->store('employees', 'public');
        } else {
            // Keep existing file if no new file uploaded
            unset($validated['image']);
        }

        // Handle file upload for image2
        if ($request->hasFile('image2')) {
            // Delete old file if exists
            if ($employee->image2) {
                \Storage::disk('public')->delete($employee->image2);
            }
            $validated['image2'] = $request->file('image2')->store('employees', 'public');
        } else {
            // Keep existing file if no new file uploaded
            unset($validated['image2']);
        }

        $employee->update($validated);
        return redirect()->route('employees.index')->with('success', 'Updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Deleted successfully');
    }
}
