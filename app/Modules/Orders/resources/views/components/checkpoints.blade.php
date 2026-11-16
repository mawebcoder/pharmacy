{{-- An order's tracker (owner, 2026-11-16): passed checkpoints ticked in primary, the current one ongoing, upcoming ones a waiting clock. See App\Modules\Orders\Support\OrderCheckpoints. <x-orders::checkpoints :checkpoints="$checkpoints" /> --}}
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Shared\Support\JalaliDate')
@props(['checkpoints'])

@php
    $ongoingIcons = [
        OrderStatus::Paid->value => 'credit-card',
        OrderStatus::Processing->value => 'cube',
        OrderStatus::OnTheWay->value => 'truck',
        OrderStatus::Cancelled->value => 'x-mark',
    ];
@endphp

<ol {{ $attributes->class(['flex flex-col md:flex-row']) }}>
    @foreach ($checkpoints as $checkpoint)
        @php
            $cancelled = $checkpoint->status === OrderStatus::Cancelled;
        @endphp
        <li wire:key="checkpoint-{{ $checkpoint->status->value }}" @if ($checkpoint->isCurrent()) aria-current="step" @endif class="relative flex flex-1 gap-3 pb-6 last:pb-0 md:flex-col md:items-center md:gap-2 md:pb-0 md:text-center">
            {{-- The line to the next checkpoint: primary once this one is passed. --}}
            @if (! $loop->last)
                <span aria-hidden="true" @class([
                    'absolute top-10 bottom-0 start-[19px] w-0.5 md:top-[19px] md:bottom-auto md:start-[calc(50%+1.5rem)] md:end-[calc(-50%+1.5rem)] md:h-0.5 md:w-auto',
                    'bg-primary-700' => $checkpoint->isPassed(),
                    'bg-line-200' => ! $checkpoint->isPassed(),
                ])></span>
            @endif

            <span @class([
                'relative grid size-10 shrink-0 place-items-center rounded-full',
                'bg-primary-700 text-white' => $checkpoint->isPassed(),
                'bg-primary-50 text-primary-700 ring-2 ring-primary-700' => $checkpoint->isCurrent() && ! $cancelled,
                'bg-danger-600/10 text-danger-600 ring-2 ring-danger-600' => $cancelled,
                'bg-surface-50 text-ink-500 ring-1 ring-line-200' => ! $checkpoint->isPassed() && ! $checkpoint->isCurrent(),
            ])>
                @if ($checkpoint->isCurrent() && ! $cancelled)
                    <span aria-hidden="true" class="absolute inset-0 rounded-full bg-primary-700/20 motion-safe:animate-ping"></span>
                @endif
                @if ($checkpoint->isPassed())
                    <x-ts-icon icon="check" class="size-5" />
                @elseif ($checkpoint->isCurrent())
                    <x-ts-icon :icon="$ongoingIcons[$checkpoint->status->value] ?? 'arrow-path'" class="relative size-5" />
                @else
                    <x-ts-icon icon="clock" class="size-5" />
                @endif
            </span>

            <div class="min-w-0 pt-1.5 md:pt-0">
                <p @class([
                    'text-sm',
                    'font-bold text-danger-600' => $cancelled,
                    'font-bold text-primary-700' => $checkpoint->isCurrent() && ! $cancelled,
                    'font-medium text-ink-900' => $checkpoint->isPassed(),
                    'text-ink-500' => ! $checkpoint->isPassed() && ! $checkpoint->isCurrent(),
                ])>{{ $checkpoint->status->label() }}</p>
                @if ($checkpoint->isCurrent() && ! $cancelled)
                    <p class="text-xs text-primary-700">{{ $checkpoint->state->label() }}</p>
                @elseif (! $checkpoint->isPassed() && ! $checkpoint->isCurrent())
                    <p class="text-xs text-ink-500">{{ $checkpoint->state->label() }}</p>
                @endif
                @if ($checkpoint->reachedAt)
                    <p class="text-xs text-ink-500">{{ JalaliDate::display($checkpoint->reachedAt, 'j F، H:i') }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
