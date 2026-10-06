<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class DateShiftService
{
    // Өнөөдрөөс хэдэн өдрийн дараа
    private const DAYS = [
        'өнөөдөр'  => 0,
        'маргааш'  => 1,
        'нөгөөдөр' => 2,
    ];

    private const WEEKDAYS = [
        'даваа'  => CarbonImmutable::MONDAY,
        'мягмар' => CarbonImmutable::TUESDAY,
        'лхагва' => CarbonImmutable::WEDNESDAY,
        'пүрэв'  => CarbonImmutable::THURSDAY,
        'баасан' => CarbonImmutable::FRIDAY,
        'бямба'  => CarbonImmutable::SATURDAY,
        'ням'    => CarbonImmutable::SUNDAY,
    ];

    // "ирэх даваа", "дараа долоо хоног" — дараагийн долоо хоногийг заана
    private const NEXT = ['дараа', 'дараагийн', 'ирэх'];

    // ням → "nm" нь ном (nm)-той ижил — номын сангийн ботод хамгийн түгээмэл үг.
    // Тиймээс "гараг" эсвэл "ирэх/дараа"-тай хамт байж л огноо гэж тооцогдоно.
    private const NEEDS_CONTEXT = ['ням'];

    private WordShiftService $words;

    public function __construct()
    {
        $this->words = new WordShiftService([
            ...array_keys(self::DAYS),
            ...array_keys(self::WEEKDAYS),
            ...self::NEXT,
            'долоо', 'хоног', 'гараг',
        ]);
    }

    public function parse(string $text, ?CarbonImmutable $now = null): ?array
    {
        $today  = ($now ?? CarbonImmutable::now('Asia/Ulaanbaatar'))->startOfDay();
        $words  = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_map(fn ($w) => $this->words->shift($w), $words);

        foreach ($tokens as $i => $token) {
            $prev     = $tokens[$i - 1] ?? null;
            $nextWeek = in_array($prev, self::NEXT, true);

            if (isset(self::DAYS[$token])) {
                return $this->result($today->addDays(self::DAYS[$token]), [$words[$i]]);
            }

            if (isset(self::WEEKDAYS[$token])) {
                $context = $nextWeek || ($tokens[$i + 1] ?? null) === 'гараг';
                if (in_array($token, self::NEEDS_CONTEXT, true) && ! $context) {
                    continue;
                }

                $weekday = self::WEEKDAYS[$token];
                $date = $nextWeek
                    ? $today->startOfWeek(CarbonImmutable::MONDAY)->addWeek()->addDays(($weekday + 6) % 7)
                    : $today->next($weekday);

                return $this->result($date, $nextWeek ? [$words[$i - 1], $words[$i]] : [$words[$i]]);
            }

            if ($token === 'хоног') {
                $start = max(0, $i - 2);
                $before = array_slice($tokens, $start, $i - $start);
                if (array_intersect($before, self::NEXT)) {
                    $date = $today->startOfWeek(CarbonImmutable::MONDAY)->addWeek();

                    return $this->result($date, array_slice($words, $start, $i - $start + 1));
                }
            }
        }

        return null;
    }

    private function result(CarbonImmutable $date, array $matched): array
    {
        return [
            'date'    => $date->toDateString(),
            'matched' => $matched,
        ];
    }
}
