{{-- A small circular loading spinner (turns clockwise, follows currentColor): <x-ui.spinner class="size-5" /> --}}
<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" {{ $attributes->class('animate-spin motion-reduce:animate-[spin_1.5s_linear_infinite]') }}>
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
</svg>
