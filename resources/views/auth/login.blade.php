<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — Contact Monitor</title>
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">

<div class="w-full max-w-sm">
    {{-- Logo / brand --}}
    <div class="flex items-center justify-center gap-2.5 mb-8">
        <img src="/logo.svg" alt="" class="w-7 h-7">
        <span class="font-bold text-lg tracking-tight text-gray-900">Contact Monitor</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">
        <h1 class="text-base font-semibold text-gray-900 mb-6">Sign in to your account</h1>

        @if($errors->any())
            <div id="login-error" role="alert" class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zm9.303-3.376c-.866 1.5.217 3.374 1.948 3.374H2.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 13.374z"/></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf

            <div>
                <label class="label" for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="input" required autofocus autocomplete="email"
                       @if($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif>
            </div>

            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" name="password"
                       class="input" required autocomplete="current-password"
                       @if($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif>
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 cursor-pointer">
                    Remember me
                </label>
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg transition mt-2">
                Sign in
            </button>
        </form>
    </div>
</div>

</body>
</html>
