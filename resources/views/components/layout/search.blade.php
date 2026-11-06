{{-- Search slot (roadmap B2 rule 2). Instant suggestions arrive in S12; for now it submits to /search. --}}
@props(['id' => 'site-search'])

<form action="/search" method="get" role="search" {{ $attributes->class('relative') }}>
    <label for="{{ $id }}" class="sr-only">جستجو در محصولات</label>
    <x-ts-icon icon="magnifying-glass" class="pointer-events-none absolute inset-y-0 start-3 my-auto size-5 text-ink-500" />
    <input
        id="{{ $id }}"
        name="q"
        type="search"
        autocomplete="off"
        placeholder="جستجوی محصول، برند یا ماده مؤثره"
        class="h-11 w-full rounded-full border-0 bg-surface-50 ps-10 pe-4 text-sm text-ink-900 ring-1 ring-line-200 placeholder:text-ink-500 focus:bg-white focus:ring-2 focus:ring-primary-700"
    >
</form>
