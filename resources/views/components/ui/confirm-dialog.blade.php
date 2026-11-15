{{--
    The storefront's confirmation dialog (owner, 2026-11-15), once in the layout: a bottom sheet on phones and a centred
    dialog from tablets up, like «مشاوره با داروساز». Asked through $store.confirm
    (resources/js/components/confirm-dialog.js); the confirm button is red, because everything asked so far removes or
    cancels something. Escape, «انصراف», a tap beside it or (on phones) dragging it down answers no.
--}}
<div x-data class="contents">
    <template x-teleport="body">
        <div x-show="$store.confirm.open" x-cloak x-on:keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)" class="fixed inset-0 z-[70] flex items-end justify-center bg-black/50 md:items-center md:p-4" dir="rtl">
            <div
                x-show="$store.confirm.open"
                x-trap.noscroll="$store.confirm.open"
                x-on:click.outside="$store.confirm.answer(false)"
                x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
                x-transition:enter-start="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                x-transition:enter-end="translate-y-0 md:scale-100 md:opacity-100"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
                aria-describedby="confirm-dialog-message"
                class="w-full rounded-t-2xl bg-card p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] text-start shadow-card md:max-w-sm md:rounded-card md:pb-5"
            >
                <div x-swipe-close="$store.confirm.answer(false)" class="-mx-5 -mt-5 mb-1 flex h-9 cursor-grab items-center justify-center md:hidden" aria-hidden="true">
                    <span class="h-1 w-10 rounded-full bg-line-300"></span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-danger-600/10 text-danger-600" aria-hidden="true">
                        <x-ts-icon icon="trash" class="size-5" x-show="$store.confirm.icon !== 'cancel'" />
                        <x-ts-icon icon="x-circle" class="size-5" x-show="$store.confirm.icon === 'cancel'" x-cloak />
                    </span>
                    <div class="min-w-0">
                        <p id="confirm-dialog-title" class="text-lg font-bold text-ink-900" x-text="$store.confirm.title"></p>
                        <p id="confirm-dialog-message" class="mt-1 text-sm leading-7 text-ink-500" x-text="$store.confirm.message" x-show="$store.confirm.message"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 md:flex-row">
                    <button type="button" x-on:click="$store.confirm.answer(false)" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-card border border-line-300 px-4 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700" x-text="$store.confirm.cancelLabel"></button>
                    <button type="button" x-on:click="$store.confirm.answer(true)" data-own-loading class="inline-flex min-h-11 flex-1 items-center justify-center rounded-card bg-danger-600 px-4 text-sm font-bold text-white hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600" x-text="$store.confirm.confirmLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>
