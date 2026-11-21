<?php

namespace App\Reports;

use App\Support\ReportPeriod;
use Carbon\CarbonInterface;

final class ReportFormat
{
    private const MONTHS = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public static function money(float|int|string|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    public static function int(float|int|string|null $value): string
    {
        return number_format((int) round((float) $value), 0, ',', '.');
    }

    public static function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($type) {
            'money' => self::money($value),
            'int' => self::int($value),
            'minutes' => number_format((float) $value, 1, ',', '.'),
            default => (string) $value,
        };
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['money', 'int', 'minutes'], true);
    }

    public static function dateTime(?CarbonInterface $date): string
    {
        return $date ? $date->copy()->timezone(config('app.timezone'))->format('d/m/Y H:i') : '-';
    }

    public static function period(ReportPeriod $period): string
    {
        return match ($period->type) {
            'month' => self::MONTHS[$period->start->month - 1].' '.$period->start->year,
            'year' => 'Tahun '.$period->start->year,
            'day' => $period->start->format('d/m/Y'),
            default => $period->start->format('d/m/Y').' - '.$period->end->format('d/m/Y'),
        };
    }

    public static function periodTag(ReportPeriod $period): string
    {
        return match ($period->type) {
            'month' => $period->start->format('Y-m'),
            'year' => $period->start->format('Y'),
            'day' => $period->start->format('Y-m-d'),
            default => $period->start->format('Y-m-d').'_'.$period->end->format('Y-m-d'),
        };
    }

    public static function bucket(string $key, string $granularity): string
    {
        return match ($granularity) {
            'hour' => sprintf('%02d:00 - %02d:59', (int) $key, (int) $key),
            'month' => self::MONTHS[(int) substr($key, 5, 2) - 1].' '.substr($key, 0, 4),
            default => substr($key, 8, 2).'/'.substr($key, 5, 2).'/'.substr($key, 0, 4),
        };
    }
}
