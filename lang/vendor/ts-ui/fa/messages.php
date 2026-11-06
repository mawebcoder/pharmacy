<?php

return [
    'environment' => [
        'environment' => 'محیط',
        'branch' => 'شاخه',
    ],

    'errors' => [
        'title' => ':count خطای اعتبارسنجی وجود دارد:',
    ],

    'select' => [
        'default' => 'یک گزینه انتخاب کنید',
        'search' => 'جستجو کنید...',
        'empty' => 'نتیجه‌ای پیدا نشد',
        'selected' => ':count مورد انتخاب شد',
    ],

    'tag' => [
        'empty' => 'نتیجه‌ای پیدا نشد',
    ],

    'autocomplete' => [
        'default' => 'برای جستجو تایپ کنید...',
        'empty' => 'نتیجه‌ای پیدا نشد',
    ],

    'toast' => [
        'button' => [
            'ok' => 'باشه',
            'confirm' => 'تأیید',
            'cancel' => 'انصراف',
        ],
    ],

    'dialog' => [
        'button' => [
            'ok' => 'باشه',
            'confirm' => 'تأیید',
            'cancel' => 'انصراف',
        ],
    ],

    'command-palette' => [
        'search' => 'جستجو...',
        'empty' => 'نتیجه‌ای پیدا نشد.',
        'navigate' => 'جابه‌جایی',
        'select' => 'انتخاب',
        'close' => 'بستن',
    ],

    'table' => [
        'empty' => 'نتیجه‌ای پیدا نشد.',
        'quantity' => 'تعداد',
        'search' => 'جستجو کنید...',
    ],

    'clipboard' => [
        'button' => [
            'copy' => 'کپی',
            'copied' => 'کپی شد!',
        ],
    ],

    'qr-code' => [
        'copy' => 'کپی',
        'download' => 'دانلود',
    ],

    'password' => [
        'rules' => [
            'title' => 'قالب مورد انتظار رمز عبور:',
            'formats' => [
                'min' => 'دست‌کم :min کاراکتر',
                'numbers' => 'دست‌کم یک عدد',
                'symbols' => 'دست‌کم یک نماد (:symbols)',
                'mixed' => 'حروف بزرگ و کوچک',
            ],
        ],
    ],

    'upload' => [
        'placeholder' => 'یک فایل انتخاب کنید',
        'size' => 'حجم',
        'upload' => 'برای بارگذاری کلیک کنید',
        'uploaded' => [
            'single' => ':count فایل ارسال شد',
            'multiple' => ':count فایل ارسال شد',
        ],
        'error' => 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.',
        'static' => [
            'empty' => [
                'title' => 'تصویری وجود ندارد.',
                'description' => 'هنوز هیچ تصویری ندارید.',
            ],
        ],
        'invalid' => 'خطای اعتبارسنجی رخ داد.',
    ],

    'upload_async' => [
        'title' => 'فایل‌ها را اینجا رها کنید',
        'description' => 'یا برای انتخاب کلیک کنید',
        'send' => 'ارسال',
        'clear' => 'پاک کردن',
        'ready' => [
            'single' => ':count فایل آماده · :size',
            'multiple' => ':count فایل آماده · :size',
        ],
        'errors' => [
            'mime' => 'نوع فایل مجاز نیست.',
            'size' => 'حجم فایل بیشتر از :max مگابایت است.',
            'limit' => 'حداکثر :max فایل می‌توانید بارگذاری کنید.',
            'network' => 'خطای شبکه. لطفاً دوباره تلاش کنید.',
            'server' => 'بارگذاری ناموفق بود. لطفاً دوباره تلاش کنید.',
            'integrity' => 'فایل ناقص دریافت شد. لطفاً دوباره تلاش کنید.',
            'unauthorized' => 'اجازه بارگذاری این فایل را ندارید.',
            'generic' => 'مشکلی پیش آمد.',
        ],
    ],

    // The TallStackUI calendar is Gregorian; these names only translate its labels.
    'date' => [
        'calendar' => [
            'months' => [
                'january' => 'ژانویه',
                'february' => 'فوریه',
                'march' => 'مارس',
                'april' => 'آوریل',
                'may' => 'مه',
                'june' => 'ژوئن',
                'july' => 'ژوئیه',
                'august' => 'اوت',
                'september' => 'سپتامبر',
                'october' => 'اکتبر',
                'november' => 'نوامبر',
                'december' => 'دسامبر',
            ],
            'week' => [
                'sunday' => 'یکشنبه',
                'monday' => 'دوشنبه',
                'tuesday' => 'سه‌شنبه',
                'wednesday' => 'چهارشنبه',
                'thursday' => 'پنجشنبه',
                'friday' => 'جمعه',
                'saturday' => 'شنبه',
            ],
        ],
        'helpers' => [
            'yesterday' => 'دیروز',
            'today' => 'امروز',
            'tomorrow' => 'فردا',
        ],
    ],

    'time' => [
        'helper' => 'زمان فعلی',
    ],

    'step' => [
        'next' => 'بعدی',
        'previous' => 'قبلی',
        'finish' => 'پایان',
    ],

    'key-value' => [
        'headers' => [
            'key' => 'کلید',
            'value' => 'مقدار',
        ],
        'placeholders' => [
            'key' => 'یک کلید وارد کنید',
            'value' => 'یک مقدار وارد کنید',
        ],
        'add-row' => 'افزودن ردیف',
        'empty' => 'ردیفی اضافه نشده است.',
    ],

    'currency' => [
        'symbol' => 'ریال',
        'currency' => 'IRR',
    ],

    'list' => [
        'search' => 'جستجو',
        'empty' => 'موردی وجود ندارد.',
    ],

    'editor' => [
        'placeholder' => 'شروع به نوشتن کنید...',
        'tooltip' => [
            'style' => 'سبک پاراگراف',
            'blockquote' => 'نقل‌قول',
            'bold' => 'پررنگ',
            'italic' => 'کج',
            'underline' => 'زیرخط',
            'strikethrough' => 'خط‌خورده',
            'ordered_list' => 'فهرست شماره‌دار',
            'unordered_list' => 'فهرست نقطه‌ای',
            'indent' => 'افزایش تورفتگی',
            'outdent' => 'کاهش تورفتگی',
            'align' => 'ترازبندی',
            'code' => 'کد',
            'code_block' => 'بلوک کد',
            'clear_format' => 'پاک کردن قالب‌بندی',
            'link' => 'درج پیوند',
            'image' => 'درج تصویر',
            'hr' => 'خط افقی',
            'undo' => 'واگرد',
            'redo' => 'ازنو',
            'fullscreen' => 'تمام‌صفحه',
        ],
        'style' => [
            'paragraph' => 'پاراگراف',
            'h1' => 'عنوان ۱',
            'h2' => 'عنوان ۲',
            'h3' => 'عنوان ۳',
        ],
        'align' => [
            'left' => 'چپ',
            'center' => 'وسط',
            'right' => 'راست',
            'justify' => 'تراز دوطرفه',
        ],
        'image' => [
            'title' => 'درج تصویر',
            'upload' => 'انتخاب فایل',
            'upload_hint' => 'PNG، JPG، GIF یا WebP تا :size',
            'or_url' => 'یا یک نشانی وارد کنید',
            'url' => 'نشانی',
            'alt' => 'متن جایگزین',
            'alt_hint' => 'تصویر را برای صفحه‌خوان‌ها توصیف کنید',
            'cancel' => 'انصراف',
            'insert' => 'درج',
            'errors' => [
                'url' => 'نشانی نامعتبر است یا در دسترس نیست.',
                'mime' => 'نوع فایل مجاز نیست.',
                'size' => 'حجم فایل بیشتر از :max کیلوبایت است.',
                'failed' => 'بارگذاری ناموفق بود. لطفاً دوباره تلاش کنید.',
            ],
        ],
        'link' => [
            'title' => 'درج پیوند',
            'text' => 'متن',
            'url' => 'نشانی',
            'cancel' => 'انصراف',
            'insert' => 'درج',
        ],
        'counters' => [
            'words' => ':count کلمه',
            'lines' => ':count خط',
        ],
    ],

    'spinner' => [
        'thinking' => 'در حال فکر کردن...',
        'loading' => 'در حال بارگذاری...',
    ],
];
