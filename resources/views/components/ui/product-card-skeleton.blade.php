{{--
    Placeholder shaped like <x-catalog::product-card> (photo well, two name lines, price, the cart row),
    for page skeletons and the search panel. Put it inside an animate-pulse parent.
    <x-ui.product-card-skeleton class="hidden md:block" />  (no display class on the root, so hidden/block work)
--}}
<div {{ $attributes->class('overflow-hidden rounded-card border border-line-200 bg-card') }}>
    <div class="mx-2.5 mt-2.5 h-24 rounded-lg bg-line-200 md:mx-3 md:mt-3 md:h-32"></div>
    <div class="p-2.5 md:p-3">
        <div class="flex h-10 flex-col justify-center gap-2"><div class="h-3.5 w-5/6 rounded-full bg-line-200"></div><div class="h-3.5 w-1/2 rounded-full bg-line-200"></div></div>
        <div class="mt-0.5 space-y-2 pt-2">
            <div class="h-11 pt-1"><div class="h-4 w-20 rounded-full bg-line-200"></div></div>
            <div class="ms-auto size-11 rounded-lg bg-line-200"></div>
        </div>
    </div>
</div>
