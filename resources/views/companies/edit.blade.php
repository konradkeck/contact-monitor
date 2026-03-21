@extends('layouts.app')
@section('title', 'Edit ' . $company->name)

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('companies.index') }}">Companies</a>
                <span class="sep">/</span>
                <a href="{{ route('companies.show', $company) }}">{{ $company->name }}</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">Edit</span>
            </nav>
            <h1 class="page-title mt-1">Edit Company</h1>
        </div>
    </div>

    <div class="card p-6">
    <form action="{{ route('companies.update', $company) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label class="label">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $company->name) }}" required class="input w-full">
            </div>

            <div>
                <label class="label">Primary Domain <span class="text-xs text-gray-400">(display only)</span></label>
                <input type="text" name="primary_domain" value="{{ old('primary_domain', $company->primary_domain) }}" class="input w-full">
            </div>

            <div>
                <label class="label">Timezone</label>
                <input type="text" name="timezone" value="{{ old('timezone', $company->timezone) }}" class="input w-full">
            </div>
        </div>

        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('companies.show', $company) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    </div>

    <div class="mt-4 pt-4 border-t border-gray-100">
        <form action="{{ route('companies.destroy', $company) }}" method="POST"
              onsubmit="return confirm('Delete this company? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete this company</button>
        </form>
    </div>
</div>
@endsection
