{{-- Slider widget: the banners placed in it (≤ 6). The first widget's first banner loads with high priority. --}}
<section aria-label="{{ $widget->getTitleFa() ?? 'بنرها' }}">
    @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => null])
    <x-content::banners :banners="$items" :priority="$first" />
</section>
