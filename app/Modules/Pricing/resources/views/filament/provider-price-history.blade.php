{{-- Every change of one supplier's price row of a SKU (provider_price_changes), newest first. --}}
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\Toman')
@use('App\Modules\Shared\Support\PersianDigits')

@php($yesNo = fn (?bool $value): string => match ($value) { true => 'بله', false => 'خیر', null => '—' })

@if ($changes->isEmpty())
    <p style="opacity: 0.7;">تغییری ثبت نشده است.</p>
@else
    <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
        <thead>
            <tr>
                @foreach (['زمان', 'رویداد', 'قیمت خرید', 'موجود نزد تأمین‌کننده', 'فروش از این تأمین‌کننده', 'زمان تحویل'] as $heading)
                    <th style="padding: 0.5rem; text-align: start; font-weight: 600; border-bottom: 1px solid rgba(127,127,127,.25);">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($changes as $change)
                <tr>
                    <td style="padding: 0.5rem; white-space: nowrap;">{{ JalaliDate::display($change->getCreatedAt(), 'Y/m/d H:i') }}</td>
                    <td style="padding: 0.5rem;">{{ $change->getEvent()->label() }}</td>
                    <td style="padding: 0.5rem; white-space: nowrap;">{{ $change->getCost() ? Toman::format($change->getCost()) : '—' }}</td>
                    <td style="padding: 0.5rem;">{{ $yesNo($change->getIsAvailable()) }}</td>
                    <td style="padding: 0.5rem;">{{ $yesNo($change->getIsEnabled()) }}</td>
                    <td style="padding: 0.5rem;">{{ $change->getLeadTimeHours() === null ? '—' : PersianDigits::toPersian($change->getLeadTimeHours()).' ساعت' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
