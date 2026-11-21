<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ReportPeriod
{
    private const MAX_RANGE_DAYS = 1100;

    private function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $granularity,
    ) {
    }

    public static function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['day', 'month', 'year', 'range'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'from' => ['required_if:period,range', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,range', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);

        return match ($request->input('period', $default)) {
            'day' => self::day($request->filled('date')
                ? CarbonImmutable::createFromFormat('!Y-m-d', $request->input('date'), $tz)
                : $now),
            'year' => self::year((int) $request->input('year', $now->year), $tz),
            'range' => self::range(
                CarbonImmutable::createFromFormat('!Y-m-d', $request->input('from'), $tz),
                CarbonImmutable::createFromFormat('!Y-m-d', $request->input('to'), $tz),
            ),
            default => self::month($request->filled('month')
                ? CarbonImmutable::createFromFormat('!Y-m', $request->input('month'), $tz)
                : $now),
        };
    }

    public static function day(CarbonImmutable $date): self
    {
        return new self('day', $date->startOfDay(), $date->endOfDay(), 'hour');
    }

    public static function month(CarbonImmutable $date): self
    {
        return new self('month', $date->startOfMonth(), $date->endOfMonth(), 'day');
    }

    public static function year(int $year, ?string $tz = null): self
    {
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $tz ?? config('app.timezone'));

        return new self('year', $start, $start->endOfYear(), 'month');
    }

    public static function range(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = $from->diffInDays($to);

        if ($days > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => ['Rentang tanggal maksimal 3 tahun.']]);
        }

        return new self('range', $from, $to, $days <= 92 ? 'day' : 'month');
    }

    public function bounds(): array
    {
        return [$this->start, $this->end];
    }

    public function bucketKeys(): array
    {
        $keys = [];

        if ($this->granularity === 'hour') {
            for ($hour = 0; $hour < 24; $hour++) {
                $keys[] = (string) $hour;
            }

            return $keys;
        }

        if ($this->granularity === 'month') {
            for ($cursor = $this->start->startOfMonth(); $cursor <= $this->end; $cursor = $cursor->addMonthNoOverflow()) {
                $keys[] = $cursor->format('Y-m');
            }

            return $keys;
        }

        for ($cursor = $this->start; $cursor <= $this->end; $cursor = $cursor->addDay()) {
            $keys[] = $cursor->format('Y-m-d');
        }

        return $keys;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'from' => $this->start->format('Y-m-d'),
            'to' => $this->end->format('Y-m-d'),
            'granularity' => $this->granularity,
        ];
    }
}
