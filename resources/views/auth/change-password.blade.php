@extends('layouts.app')
@section('title', 'Change Password')

@section('content')
<div class="max-w-md">
    <div class="page-header">
        <h1 class="page-title">Change Password</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 px-6 py-6">
        <form method="POST" action="{{ route('auth.change-password.post') }}" class="space-y-4">
            @csrf

            <div>
                <label class="label" for="current_password">Current password</label>
                <input id="current_password" type="password" name="current_password"
                       class="input" required autocomplete="current-password">
                @error('current_password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="password">New password</label>
                <input id="password" type="password" name="password"
                       class="input" required autocomplete="new-password">
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary w-full py-2.5 justify-center">
                Change Password
            </button>
        </form>
    </div>
</div>
@endsection
