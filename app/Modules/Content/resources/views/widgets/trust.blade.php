{{-- Trust widget: licences, pharmacist, delivery, returns, with the live trust counters (the same block as the footer). --}}
<section aria-label="{{ $widget->getTitleFa() ?? 'چرا از ما بخرید' }}" class="rounded-card border border-line-200 bg-card p-4 md:p-6">
    @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => null])
    <x-layout.trust :slider="true" />
    <x-layout.trust-counters class="mt-6 border-t border-line-200 pt-6" />
</section>
