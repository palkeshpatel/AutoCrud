@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Employee_Sals</h1>
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <a href="{{ route('employee_sals.create') }}" class="btn btn-primary mb-3">Create New</a>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Salary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employee_sals as $employee_sal)
                        <tr>
                            <td>{{ $employee_sal->id }}</td>
                            <td>{{ $employee_sal->salary }}</td>
                            <td>
                                <a href="{{ route('employee_sals.show', $employee_sal) }}" class="btn btn-sm btn-info">View</a>
                                <a href="{{ route('employee_sals.edit', $employee_sal) }}" class="btn btn-sm btn-warning">Edit</a>
                                <form action="{{ route('employee_sals.destroy', $employee_sal) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">No records found</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $employee_sals->links() }}
        </div>
    </div>
</div>
@endsection
