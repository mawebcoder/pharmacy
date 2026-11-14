{{-- An order's status as a badge (roadmap B10): <x-orders::status-badge :status="$order->getStatus()" /> --}}
@props(['status'])

@php
    $classes = match ($status->color()) {
        'warning' => 'bg-warning-500/15 text-ink-900',
        'primary' => 'bg-primary-50 text-primary-800',
        'info' => 'bg-primary-100 text-primary-900',
        'success' => 'bg-success-600/10 text-success-600',
        default => 'bg-surface-50 text-ink-500 ring-1 ring-line-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 text-xs leading-6 font-bold', $classes]) }}>{{ $status->label() }}</span>
