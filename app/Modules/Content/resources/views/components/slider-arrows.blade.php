{{--
    The previous and next arrows of a Swiper slider (resources/js/components/slider.js), centred on its sides: previous
    on the start (right) edge, next on the end (left) edge. Put inside the relative element around the slider.
    Arrows Swiper disables (at the ends of a row) or locks (everything fits) are hidden.
    <x-content::slider-arrows previous="بنر قبلی" next="بنر بعدی" />
--}}
@props([
    'previous',
    'next',
])

@php($arrow = 'absolute top-1/2 z-10 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink-900 shadow-card hover:bg-white focus-visible:outline-2 focus-visible:outline-primary-700 [&.swiper-button-disabled]:hidden [&.swiper-button-lock]:hidden')

<button type="button" x-ref="previous" class="{{ $arrow }} start-2 md:start-3" aria-label="{{ $previous }}">
    <x-ts-icon icon="chevron-right" class="size-5" />
</button>
<button type="button" x-ref="next" class="{{ $arrow }} end-2 md:end-3" aria-label="{{ $next }}">
    <x-ts-icon icon="chevron-left" class="size-5" />
</button>
