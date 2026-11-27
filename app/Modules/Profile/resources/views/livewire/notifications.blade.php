{{-- «اعلان‌ها» (owner, 2026-11-21): see App\Modules\Profile\Livewire\Notifications. --}}
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

<x-profile::account-shell active="notifications" title="اعلان‌ها">
    @if ($notifications->isEmpty())
        <x-identity::empty-state icon="bell" message="هنوز اعلانی ندارید." hint="پیامک‌هایی که درباره سفارش‌هایتان می‌فرستیم اینجا هم نمایش داده می‌شوند." />
    @else
        @if ($unread > 0)
            <div class="flex justify-end">
                <button type="button" wire:click="markAllRead" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm font-medium text-primary-700 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="check" class="size-5" />
                    همه خوانده شد
                </button>
            </div>
        @endif
        <ul class="space-y-2">
            @foreach ($notifications as $notification)
                @php($unreadOne = $notification->read_at === null)
                <li wire:key="notification-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" @class([
                        'w-full rounded-card border p-4 text-start focus-visible:outline-2 focus-visible:outline-primary-700',
                        'border-primary-700/30 bg-primary-50' => $unreadOne,
                        'border-line-200 bg-card hover:border-primary-700' => ! $unreadOne,
                    ])>
                        <span class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 font-bold text-ink-900">
                                @if ($unreadOne)
                                    <span class="size-2 shrink-0 rounded-full bg-primary-700" aria-hidden="true"></span>
                                    <span class="sr-only">خوانده نشده:</span>
                                @endif
                                {{ $notification->data['title'] ?? 'اعلان' }}
                            </span>
                            <span class="shrink-0 text-xs text-ink-500">{{ JalaliDate::display($notification->created_at, 'j F، H:i') }}</span>
                        </span>
                        <span class="mt-1 block text-sm leading-7 text-ink-900">{{ PersianDigits::toPersian((string) ($notification->data['body'] ?? '')) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        {{ $notifications->links() }}
    @endif
</x-profile::account-shell>
