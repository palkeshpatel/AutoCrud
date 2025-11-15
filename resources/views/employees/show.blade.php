@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Employee Details</h1>
            <table class="table">
                <tr><th>ID</th><td>{{ $employee->id }}</td></tr>
                <tr><th>Name</th><td>{{ $employee->name }}</td></tr>
                <tr><th>Detail</th><td>{{ $employee->detail }}</td></tr>
                <tr><th>Image</th><td>
                    @if($employee->image)
                        <img src="{{ asset('storage/' . $employee->image) }}" alt="Image" class="img-fluid" style="max-width: 300px; max-height: 300px; object-fit: cover; border-radius: 8px;">
                        <br><small class="text-muted">{{ $employee->image }}</small>
                    @else
                        <span class="text-muted">No image</span>
                    @endif
                </td></tr>
                <tr><th>Image2</th><td>
                    @if($employee->image2)
                        <img src="{{ asset('storage/' . $employee->image2) }}" alt="Image2" class="img-fluid" style="max-width: 300px; max-height: 300px; object-fit: cover; border-radius: 8px;">
                        <br><small class="text-muted">{{ $employee->image2 }}</small>
                    @else
                        <span class="text-muted">No image</span>
                    @endif
                </td></tr>
                <tr><th>Created At</th><td>{{ $employee->created_at }}</td></tr>
                <tr><th>Updated At</th><td>{{ $employee->updated_at }}</td></tr>
            </table>
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-warning">Edit</a>
            <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
            <a href="{{ route('employees.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
@endsection
