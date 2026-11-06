{{-- One dismissible announcement line (roadmap B9.1, B8.2). Text comes from settings in S35. --}}
@props(['text' => 'ارسال رایگان برای سفارش‌های بالای ۵۰۰٬۰۰۰ تومان'])

<div x-data="{ open: true }" x-show="open" class="bg-primary-800 text-white">
    <div class="mx-auto flex h-8 max-w-7xl items-center justify-center gap-2 px-4 text-xs md:px-6 xl:px-8">
        <p class="truncate">{{ $text }}</p>
        <button type="button" x-on:click="open = false" class="-my-1.5 -me-3 grid size-11 place-items-center rounded-full hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white" aria-label="بستن اطلاعیه">
            <x-ts-icon icon="x-mark" class="size-4" />
        </button>
    </div>
</div>
