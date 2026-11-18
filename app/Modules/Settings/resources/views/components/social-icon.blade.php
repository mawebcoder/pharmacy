{{--
    One social network's mark for the footer (roadmap S30), drawn in currentColor at the size of its class.
    <x-settings::social-icon :platform="SocialPlatform::Instagram" class="size-5" />
--}}
@props(['platform'])

@use('App\Modules\Settings\Enums\SocialPlatform')

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($platform)
        @case(SocialPlatform::Instagram)
            <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor" />
            @break
        @case(SocialPlatform::Telegram)
            <path d="M21 4 3 11l6 2 2 6 3-4 5 4z" /><path d="m9 13 8-6" />
            @break
        @case(SocialPlatform::WhatsApp)
            <path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z" /><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1a4 4 0 0 1-2-2l1-1-1-2z" />
            @break
        @case(SocialPlatform::YouTube)
            <rect x="2.5" y="5.5" width="19" height="13" rx="4" /><path d="m10 9 5 3-5 3z" fill="currentColor" />
            @break
        @case(SocialPlatform::Aparat)
            <circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="7.5" r="1.6" fill="currentColor" /><circle cx="12" cy="16.5" r="1.6" fill="currentColor" /><circle cx="7.5" cy="12" r="1.6" fill="currentColor" /><circle cx="16.5" cy="12" r="1.6" fill="currentColor" />
            @break
        @case(SocialPlatform::LinkedIn)
            <rect x="3" y="3" width="18" height="18" rx="3" /><path d="M8 10.5V16M8 7.5v.01M12 16v-5.5M12 13a2.5 2.5 0 0 1 5 0v3" />
            @break
        @case(SocialPlatform::X)
            <path d="m4 4 16 16M20 4 4 20" />
            @break
        @default
            <path d="M4 5h16v11H9l-5 4z" />
    @endswitch
</svg>
