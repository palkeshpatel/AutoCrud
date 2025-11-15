@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Student Details</h1>
            <table class="table">
                <tr><th>ID</th><td>{{ $student->id }}</td></tr>
                <tr><th>T1</th><td>{{ $student->t1 }}</td></tr>
                <tr><th>Created At</th><td>{{ $student->created_at }}</td></tr>
                <tr><th>Updated At</th><td>{{ $student->updated_at }}</td></tr>
            </table>
            <a href="{{ route('students.edit', $student) }}" class="btn btn-warning">Edit</a>
            <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
            <a href="{{ route('students.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
@endsection
