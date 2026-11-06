<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class JalaliDateTest extends TestCase
{
    public function test_the_application_runs_on_tehran_time(): void
    {
        $this->assertSame('Asia/Tehran', config('app.timezone'));
        $this->assertSame('Asia/Tehran', now()->timezoneName);
    }

    public function test_carbon_dates_are_shown_in_jalali(): void
    {
        $date = Carbon::parse('2026-11-06 14:30');

        $this->assertSame('1405/08/15', $date->toJalali());
        $this->assertSame('1405/08/15 14:30', $date->toJalali('Y/m/d H:i'));
    }

    public function test_nowruz_is_the_first_day_of_the_jalali_year(): void
    {
        $this->assertSame('1405/01/01', Carbon::parse('2026-03-21')->toJalali());
        $this->assertSame('1404/12/29', Carbon::parse('2026-03-20')->toJalali());
    }

    public function test_the_date_picker_binds_a_gregorian_value_with_jalali_limits(): void
    {
        $html = (string) $this->blade(
            '<x-jalali-date label="تاریخ تحویل" name="delivery_date" value="2026-11-11" :min="$min" max="2026-12-06" />',
            ['min' => Carbon::parse('2026-11-06')],
        );

        $this->assertStringContainsString('x-data="jalaliDate(', $html);
        $this->assertStringContainsString('2026-11-11', $html);
        $this->assertStringContainsString('2026-11-06', $html);
        $this->assertStringContainsString('2026-12-06', $html);
        $this->assertStringContainsString('name="delivery_date"', $html);
        $this->assertStringContainsString('<label for="jalali-date-delivery-date"', $html);
    }

    public function test_the_date_picker_shows_validation_errors(): void
    {
        $this->withViewErrors(['delivery_date' => 'تاریخ تحویل الزامی است.']);

        $this->blade('<x-jalali-date name="delivery_date" hint="راهنما" />')
            ->assertSee('تاریخ تحویل الزامی است.')
            ->assertDontSee('راهنما');
    }
}
