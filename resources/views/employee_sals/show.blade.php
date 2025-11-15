@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Employee_Sal Details</h1>
            <table class="table">
                <tr><th>ID</th><td>{{ $employee_sal->id }}</td></tr>
                <tr><th>Employee</th><td>
                    @if($employee_sal->employee)
                        <a href="{{ route('employees.show', $employee_sal->employee) }}">{{ $employee_sal->employee->name ?? $employee_sal->employee->id }}</a>
                    @else
                        <span class="text-muted">No Employee</span>
                    @endif
                </td></tr>
                <tr><th>Salary</th><td>{{ $employee_sal->salary }}</td></tr>
                <tr><th>Created At</th><td>{{ $employee_sal->created_at }}</td></tr>
                <tr><th>Updated At</th><td>{{ $employee_sal->updated_at }}</td></tr>
            </table>
            <a href="{{ route('employee_sals.edit', $employee_sal) }}" class="btn btn-warning">Edit</a>
            <form action="{{ route('employee_sals.destroy', $employee_sal) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
            <a href="{{ route('employee_sals.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
@endsection
