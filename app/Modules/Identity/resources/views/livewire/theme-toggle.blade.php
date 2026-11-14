{{-- Header light/dark switch: see App\Modules\Identity\Livewire\ThemeToggle. The icon shows the theme it switches to. --}}
<div>
    <button
        type="button"
        x-data
        x-on:click="const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark'; window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: next } })); $wire.choose(next)"
        class="grid size-11 place-items-center rounded-full text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700"
    >
        <x-ts-icon icon="moon" class="size-6 dark:hidden" aria-hidden="true" />
        <x-ts-icon icon="sun" class="hidden size-6 dark:block" aria-hidden="true" />
        <span class="sr-only dark:hidden">حالت تیره</span>
        <span class="sr-only hidden dark:inline">حالت روشن</span>
    </button>
</div>
