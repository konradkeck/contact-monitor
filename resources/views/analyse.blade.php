<!DOCTYPE html>
<html lang="en" class="h-full overflow-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Analyse</title>
    @vite(['resources/css/app.css', 'resources/js/analyse/app.js'])
    <style>#app { height: 100%; }</style>
</head>
<body class="h-full overflow-hidden bg-gray-950 text-gray-100 antialiased">
    @inertia
</body>
</html>
