{{--
    The layer that dims and blurs the page below the header while a header panel is open (owner, 2026-11-14/15): the
    search panel (also the one on /search), the mega menu, the mini cart and the account menu. It starts at the site
    header's bottom edge, measured when it shows and on scroll, not at --header-height: above the scrolled-to-top header
    sits the announcement, and a layer reaching over the header would cover what opened it. Only opacity animates. A
    click on it runs $close.
    Put it inside the panel's x-data: <x-layout.header-backdrop show="open" close="open = false" class="hidden xl:block" />
    Extra attributes (e.g. x-on:mouseenter) go on the layer; layer="z-40" lifts it where the panel needs that.
--}}
@props(['show', 'close', 'layer' => 'z-30'])

<div
    x-data="{ below: 0, measure() { this.below = document.querySelector('header')?.getBoundingClientRect().bottom ?? 0 } }"
    x-effect="if ({{ $show }}) measure()"
    x-on:scroll.window.passive="({{ $show }}) && measure()"
    x-show="{{ $show }}"
    x-cloak
    x-on:click="{{ $close }}"
    x-transition:enter="transition-opacity duration-150 ease-out motion-reduce:transition-none"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-100 ease-in motion-reduce:transition-none"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-bind:style="{ top: below + 'px' }"
    aria-hidden="true"
    {{ $attributes->class(['fixed inset-x-0 bottom-0 bg-ink-900/25 backdrop-blur-[3px] [contain:strict]', $layer]) }}
></div>
