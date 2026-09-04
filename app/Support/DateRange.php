<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Global dashboard date filter: today | yesterday | 7d | 30d | month | last_month | year | custom
 * with "compare with previous period" support.
 */
class DateRange
{
    public Carbon $from;
    public Carbon $to;
    public Carbon $prevFrom;
    public Carbon $prevTo;
    public string $preset;
    public bool $compare;

    public const PRESETS = [
        'today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 Days', '28d' => 'Last 28 Days', '30d' => 'Last 30 Days',
        'month' => 'Current Month', 'last_month' => 'Previous Month', '3m' => 'Last 3 Months', 'year' => 'Current Year', 'custom' => 'Custom Range',
    ];

    public static function fromRequest(Request $request, string $default = '30d'): self
    {
        $r = new self;
        $r->preset = $request->query('range', $default);
        if (! isset(self::PRESETS[$r->preset])) {
            $r->preset = $default;
        }
        $r->compare = filter_var($request->query('compare', '0'), FILTER_VALIDATE_BOOLEAN);
        $today = now()->startOfDay();
        [$r->from, $r->to] = match ($r->preset) {
            'today' => [$today->copy(), $today->copy()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '28d' => [$today->copy()->subDays(27), $today->copy()],
            '30d' => [$today->copy()->subDays(29), $today->copy()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            '3m' => [$today->copy()->subMonths(3), $today->copy()],
            'year' => [$today->copy()->startOfYear(), $today->copy()],
            'custom' => [
                self::parse($request->query('from'), $today->copy()->subDays(29)),
                self::parse($request->query('to'), $today->copy()),
            ],
        };
        if ($r->from->gt($r->to)) {
            [$r->from, $r->to] = [$r->to, $r->from];
        }
        if ($r->from->lt($today->copy()->subYears(3))) {
            $r->from = $today->copy()->subYears(3);
        }
        $days = $r->from->diffInDays($r->to) + 1;
        $r->prevTo = $r->from->copy()->subDay();
        $r->prevFrom = $r->prevTo->copy()->subDays($days - 1);
        return $r;
    }

    protected static function parse(?string $v, Carbon $fallback): Carbon
    {
        try {
            return $v ? Carbon::parse($v)->startOfDay() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function days(): int
    {
        return $this->from->diffInDays($this->to) + 1;
    }

    public function label(): string
    {
        if ($this->preset === 'custom' || $this->days() > 1) {
            return $this->from->format('M j, Y').' – '.$this->to->format('M j, Y');
        }
        return $this->from->format('M j, Y');
    }

    public function query(array $extra = []): array
    {
        return array_merge(['range' => $this->preset, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'compare' => $this->compare ? 1 : 0], $extra);
    }
}
