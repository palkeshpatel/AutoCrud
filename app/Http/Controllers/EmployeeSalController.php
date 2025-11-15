<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSal;
use Illuminate\Http\Request;

class EmployeeSalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employee_sals = EmployeeSal::latest()->paginate(10);
        return view('employee_sals.index', compact('employee_sals'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employee_sals.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'salary' => 'string|max:255',
        ]);

        EmployeeSal::create($validated);
        return redirect()->route('employee_sals.index')->with('success', 'Created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeSal $employee_sal)
    {
        return view('employee_sals.show', compact('employee_sal'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeeSal $employee_sal)
    {
        return view('employee_sals.edit', compact('employee_sal'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeeSal $employee_sal)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'salary' => 'string|max:255',
        ]);

        $employee_sal->update($validated);
        return redirect()->route('employee_sals.index')->with('success', 'Updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeSal $employee_sal)
    {
        $employee_sal->delete();
        return redirect()->route('employee_sals.index')->with('success', 'Deleted successfully');
    }
}
