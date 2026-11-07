<?php

declare(strict_types=1);

// Persian strings missing from Filament's own fa translation; merged over it by Laravel.
return [
    'column_manager' => [
        'position' => 'جایگاه :position از :count.',
        'position_in_group' => 'جایگاه :position از :count در :group.',
        'reordered' => ':column به جایگاه :position از :count منتقل شد.',
        'reordered_deferred' => ':column به جایگاه :position از :count منتقل شد. برای ذخیره تغییرات، ستون‌ها را اعمال کنید.',
        'reordered_in_group' => ':column به جایگاه :position از :count در :group منتقل شد.',
        'reordered_in_group_deferred' => ':column به جایگاه :position از :count در :group منتقل شد. برای ذخیره تغییرات، ستون‌ها را اعمال کنید.',
        'actions' => [
            'move_up' => [
                'label' => 'انتقال :column به بالا',
            ],
            'move_down' => [
                'label' => 'انتقال :column به پایین',
            ],
            'reorder' => [
                'label' => 'مرتب‌سازی ستون',
            ],
        ],
    ],
    'columns' => [
        'editable' => [
            'saving' => 'در حال ذخیره تغییر...',
            'saved' => 'تغییر ذخیره شد.',
            'failure' => 'ذخیره شدن تغییر تأیید نشد. پیش از تلاش دوباره، صفحه را تازه کنید.',
            'unavailable' => 'این مورد دیگر قابل ویرایش نیست.',
        ],
        'icon' => [
            'boolean' => [
                'true' => 'بله',
                'false' => 'خیر',
            ],
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'مرتب‌سازی مورد :key',
        ],
        'move_record_up' => [
            'label' => 'انتقال :label به بالا',
        ],
        'move_record_down' => [
            'label' => 'انتقال :label به پایین',
        ],
        'toggle_record_content' => [
            'label' => 'باز یا بسته کردن مورد :key',
        ],
    ],
    'loading' => 'در حال بارگذاری...',
    'reordering' => [
        'position' => ':label، جایگاه :position از :count.',
        'moved' => ':label به جایگاه :position از :count منتقل شد.',
    ],
    'result_count' => '{0} نتیجه‌ای یافت نشد|[1,*] :count نتیجه',
];
