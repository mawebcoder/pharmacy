<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @fonts
        <tallstackui:script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-surface font-sans text-ink antialiased">
        <x-ts-toast />
        <x-ts-dialog />

        {{ $slot }}

        @livewireScripts
    </body>
</html>
