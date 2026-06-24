<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface antialiased">
    <div class="flex min-h-screen">
        <div class="splis-login-panel">
            <div class="splis-login-grid"></div>
            <div class="relative z-10 flex flex-col justify-between p-12 text-white">
                <div>
                    <img src="{{ asset('images/bataan-seal.png') }}" alt="Province of Bataan official seal" class="mb-8 h-16 w-16 rounded-full object-cover ring-2 ring-white/20">
                    <h1 class="max-w-md text-4xl font-semibold leading-tight tracking-tight">PGB - SPLIS</h1>
                    <p class="mt-2 text-lg text-slate-300">Sangguniang Panlalawigan</p>
                    <p class="mt-4 max-w-md text-base text-slate-400">Legislative Information System for ordinances, resolutions, and archival records.</p>
                </div>
                <div class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-sm">
                            <p class="text-2xl font-bold text-white">11K+</p>
                            <p class="mt-1 text-sm text-slate-400">Archived resolutions</p>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-sm">
                            <p class="text-2xl font-bold text-white">Secure</p>
                            <p class="mt-1 text-sm text-slate-400">Role-based access control</p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500">Sangguniang Panlalawigan · Bataan</p>
                </div>
            </div>
        </div>

        <div class="flex flex-1 items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden text-center">
                    <img src="{{ asset('images/bataan-seal.png') }}" alt="Province of Bataan official seal" class="mx-auto mb-4 h-14 w-14 rounded-full object-cover ring-2 ring-brand-900/10">
                    <h2 class="text-2xl font-semibold text-slate-900">PGB - SPLIS</h2>
                    <p class="mt-1 text-sm text-slate-500">Sangguniang Panlalawigan</p>
                </div>

                <div class="splis-card splis-card-body">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-slate-900">Welcome back</h2>
                        <p class="mt-1 text-sm text-slate-500">Sign in to access the legislative repository.</p>
                    </div>

                    @if ($errors->any())
                        <div class="splis-alert-error mb-6">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="username" class="splis-label">Username</label>
                            <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus class="splis-input">
                        </div>
                        <div>
                            <label for="password" class="splis-label">Password</label>
                            <input type="password" name="password" id="password" required class="splis-input">
                        </div>
                        <label class="flex items-center gap-2.5 text-sm text-slate-600">
                            <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Keep me signed in
                        </label>
                        <button type="submit" class="splis-btn-primary w-full">
                            Sign in to SPLIS
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
