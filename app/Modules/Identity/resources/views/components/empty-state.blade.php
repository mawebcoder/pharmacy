{{-- An empty account section (roadmap B13 empty state): icon, message, and a link back to the shop. --}}
@props(['icon', 'message', 'hint' => null, 'actionLabel' => 'رفتن به فروشگاه', 'actionHref' => null])

<div class="rounded-card border border-line-200 bg-white px-6 py-12 text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary-50 text-primary-700">
        <x-ts-icon :icon="$icon" class="size-7" />
    </span>
    <p class="mt-4 font-bold text-ink-900">{{ $message }}</p>
    @if ($hint)
        <p class="mt-1 text-sm text-ink-500">{{ $hint }}</p>
    @endif
    {{ $slot }}
    <a href="{{ $actionHref ?? route('home') }}" wire:navigate class="mt-5 inline-flex min-h-11 items-center rounded-card border border-primary-700 px-5 text-sm font-medium text-primary-700 hover:bg-primary-50">
        {{ $actionLabel }}
    </a>
</div>
