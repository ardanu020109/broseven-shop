<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Reports\ReportFormat;
use App\Rules\StrongPassword;
use App\Support\Color;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CoreRulesTest extends TestCase
{
    public function test_order_status_transitions_follow_the_specification(): void
    {
        $this->assertTrue(OrderStatus::DRAFT->canTransitionTo(OrderStatus::MENUNGGU));
        $this->assertTrue(OrderStatus::DRAFT->canTransitionTo(OrderStatus::DIBATALKAN));
        $this->assertTrue(OrderStatus::MENUNGGU->canTransitionTo(OrderStatus::SELESAI));
        $this->assertTrue(OrderStatus::MENUNGGU->canTransitionTo(OrderStatus::DIBATALKAN));

        $this->assertFalse(OrderStatus::DRAFT->canTransitionTo(OrderStatus::SELESAI));
        $this->assertFalse(OrderStatus::SELESAI->canTransitionTo(OrderStatus::DIBATALKAN));
        $this->assertFalse(OrderStatus::DIBATALKAN->canTransitionTo(OrderStatus::MENUNGGU));
        $this->assertTrue(OrderStatus::SELESAI->isFinal());
        $this->assertTrue(OrderStatus::DIBATALKAN->isFinal());
        $this->assertFalse(OrderStatus::MENUNGGU->isFinal());
    }

    public function test_roles_are_exactly_the_three_defined_roles(): void
    {
        $this->assertSame(['superadmin', 'kasir', 'admin_master'], Role::values());
    }

    public function test_strong_password_rule(): void
    {
        $check = fn (string $value) => Validator::make(['p' => $value], ['p' => [new StrongPassword]])->fails();

        $this->assertTrue($check('abc1'));
        $this->assertTrue($check('abcdefgh'));
        $this->assertTrue($check('12345678'));
        $this->assertTrue($check(str_repeat('a1', 40)));
        $this->assertFalse($check('abcdefg1'));
        $this->assertFalse($check('Password123'));
    }

    public function test_color_contrast(): void
    {
        $this->assertEqualsWithDelta(21.0, Color::contrast('#000000', '#FFFFFF'), 0.01);
        $this->assertGreaterThanOrEqual(3, Color::contrast('#26547C', '#FFFFFF'));
        $this->assertLessThan(3, Color::contrast('#F0F0F0', '#FFFFFF'));
    }

    public function test_report_formatting(): void
    {
        $this->assertSame('Rp 1.234.567', ReportFormat::money(1234567));
        $this->assertSame('1.234', ReportFormat::int(1234));
        $this->assertSame('-', ReportFormat::cell(null, 'money'));
        $this->assertSame('Rp 0', ReportFormat::cell(0, 'money'));
        $this->assertSame('09:00 - 09:59', ReportFormat::bucket('9', 'hour'));
        $this->assertSame('November 2026', ReportFormat::bucket('2026-11', 'month'));
        $this->assertSame('05/11/2026', ReportFormat::bucket('2026-11-05', 'day'));
    }

    public function test_report_period_buckets_and_granularity(): void
    {
        $tz = config('app.timezone');

        $this->assertCount(24, ReportPeriod::day(CarbonImmutable::parse('2026-11-10', $tz))->bucketKeys());
        $this->assertCount(30, ReportPeriod::month(CarbonImmutable::parse('2026-11-15', $tz))->bucketKeys());
        $this->assertCount(12, ReportPeriod::year(2026, $tz)->bucketKeys());

        $short = ReportPeriod::range(CarbonImmutable::parse('2026-01-01', $tz), CarbonImmutable::parse('2026-03-31', $tz));
        $long = ReportPeriod::range(CarbonImmutable::parse('2026-01-01', $tz), CarbonImmutable::parse('2026-12-31', $tz));

        $this->assertSame('day', $short->granularity);
        $this->assertSame('month', $long->granularity);
    }

    public function test_report_period_rejects_ranges_longer_than_three_years(): void
    {
        $tz = config('app.timezone');

        $this->expectException(ValidationException::class);

        ReportPeriod::range(CarbonImmutable::parse('2020-01-01', $tz), CarbonImmutable::parse('2026-01-01', $tz));
    }
}