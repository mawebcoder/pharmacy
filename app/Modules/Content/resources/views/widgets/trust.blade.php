{{-- Trust widget: licences, pharmacist, delivery, returns (the same block as the footer). --}}
<section aria-label="{{ $widget->getTitleFa() ?? 'چرا از ما بخرید' }}" class="rounded-card border border-line-200 bg-white p-4 md:p-6">
    @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => null])
    <x-layout.trust :slider="true" />
</section>
