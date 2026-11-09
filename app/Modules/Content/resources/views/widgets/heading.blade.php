{{-- A widget's heading with an optional «مشاهده همه» link. Variables: $title (nullable), $href (nullable). --}}
@if ($title !== null || $href !== null)
    <div class="mb-3 flex min-h-11 items-center justify-between gap-3">
        @if ($title !== null)
            <h2 class="text-lg font-bold text-ink-900 xl:text-xl">{{ $title }}</h2>
        @endif
        @if ($href !== null)
            <a href="{{ $href }}" wire:navigate class="ms-auto flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                مشاهده همه
                <x-ts-icon icon="chevron-left" class="size-4" />
            </a>
        @endif
    </div>
@endif
