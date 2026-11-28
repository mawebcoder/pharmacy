{{--
    TallStackUI's scripts (in place of <tallstackui:script />, which loads every one of them on every page). The chart,
    clipboard, date, editor and upload scripts belong to components the storefront does not use (about 63 KB), so they
    are left out. Using one of those components? Take its name out of $unusedScripts.
--}}
@php
    $unusedScripts = ['chart', 'clipboard', 'date', 'editor', 'upload'];
@endphp
{!! preg_replace(
    '#<script type="module" src="/tallstackui/script/tallstackui-(?:'.implode('|', $unusedScripts).')-[^"]+\.js"></script>#',
    '',
    \TallStackUi\Facades\TallStackUi::directives()->script(),
) !!}
