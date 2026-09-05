<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full bg-gray-100 font-sans">
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-card bg-primary-600 text-sm font-bold text-white">
                    CMS
                </span>
                <div class="text-center">
                    <p class="text-lg font-semibold text-gray-900">{{ config('app.name') }}</p>
                    <p class="text-sm text-gray-500">Back-office company profile</p>
                </div>
            </div>

            <div class="rounded-card border border-gray-200 bg-white p-6 shadow-card sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </div>
</body>
</html>
