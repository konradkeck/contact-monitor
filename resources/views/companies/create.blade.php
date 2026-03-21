@extends('layouts.app')
@section('title', 'New Company')

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('companies.index') }}">Companies</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">New Company</span>
            </nav>
            <h1 class="page-title mt-1">New Company</h1>
        </div>
    </div>

    <div class="card p-6">
    <form action="{{ route('companies.store') }}" method="POST">
        @csrf

        <div class="space-y-4">
            <div>
                <label class="label">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="input w-full">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Primary Domain</label>
                <input type="text" name="primary_domain" value="{{ old('primary_domain') }}" placeholder="example.com" class="input w-full">
            </div>

            <div>
                <label class="label">Timezone</label>
                <input type="text" name="timezone" value="{{ old('timezone', 'Europe/Warsaw') }}" placeholder="Europe/Warsaw" class="input w-full">
            </div>
        </div>

        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Create Company</button>
            <a href="{{ route('companies.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
