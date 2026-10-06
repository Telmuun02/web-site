<?php

namespace Tests\Unit;

use App\Services\DateShiftService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateShiftServiceTest extends TestCase
{
    private DateShiftService $service;

    protected function setUp(): void
    {
        $this->service = new DateShiftService();
    }

    // 2026-10-03 бямба гараг гэж үзнэ
    private function saturday(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-03 10:00', 'Asia/Ulaanbaatar');
    }

    public static function fromSaturday(): array
    {
        return [
            // өнөөдөр / маргааш / нөгөөдөр
            ['өнөөдөр ирнэ', '2026-10-03'],
            ['маргааш', '2026-10-04'],
            ['margaash ireh uu', '2026-10-04'],
            ['mrgsh', '2026-10-04'],
            ['маргаашийн', '2026-10-04'],
            ['nuguudur', '2026-10-05'],
            // гарагууд — ирээдүйн хамгийн ойрын
            ['davaa', '2026-10-05'],
            ['Мягмар гарагт', '2026-10-06'],
            ['lkhagva', '2026-10-07'],
            ['purev', '2026-10-08'],
            ['baasand', '2026-10-09'],
            ['byamba', '2026-10-10'],   // өнөөдөр бямба — дараагийнх
            ['ням гарагт', '2026-10-04'],
            // дараагийн долоо хоног — даваа гарагаас
            ['дараа долоо хоног', '2026-10-05'],
            ['ireh 7 honog', '2026-10-05'],
        ];
    }

    #[DataProvider('fromSaturday')]
    public function test_parses_date(string $text, string $expected): void
    {
        $this->assertSame($expected, $this->service->parse($text, $this->saturday())['date']);
    }

    // Даваа гарагт "лхагва" нь энэ долоо хоногийнх, "ирэх лхагва" нь дараагийнх
    public function test_next_week_weekday(): void
    {
        $monday = CarbonImmutable::parse('2026-10-05', 'Asia/Ulaanbaatar');

        $this->assertSame('2026-10-07', $this->service->parse('лхагва', $monday)['date']);
        $this->assertSame('2026-10-14', $this->service->parse('ирэх лхагва', $monday)['date']);
        $this->assertSame('2026-10-12', $this->service->parse('дараа долоо хоног', $monday)['date']);
    }

    public function test_returns_matched_words(): void
    {
        $this->assertSame(['ireh', 'davaa'], $this->service->parse('ireh davaa uulzah', $this->saturday())['matched']);
    }

    public function test_returns_matched_phrase_for_next_week(): void
    {
        $this->assertSame(['дараа', 'долоо', 'хоног'], $this->service->parse('дараа долоо хоног уулзъя', $this->saturday())['matched']);
    }

    // Нэг текстэд хэд хэдэн огноо байвал эхнийх нь
    public function test_first_date_wins(): void
    {
        $this->assertSame('2026-10-04', $this->service->parse('маргааш эсвэл нөгөөдөр', $this->saturday())['date']);
    }

    // Сар, жил дамжих
    public function test_rolls_over_month_and_year(): void
    {
        $newYearsEve = CarbonImmutable::parse('2026-12-31', 'Asia/Ulaanbaatar');   // пүрэв

        $this->assertSame('2027-01-01', $this->service->parse('маргааш', $newYearsEve)['date']);
        $this->assertSame('2027-01-04', $this->service->parse('даваа', $newYearsEve)['date']);
        $this->assertSame('2027-01-04', $this->service->parse('ирэх долоо хоног', $newYearsEve)['date']);
    }

    // Ням гарагт: долоо хоног даваагаас эхэлдэг тул "ирэх даваа" = маргааш
    public function test_sunday_edge(): void
    {
        $sunday = CarbonImmutable::parse('2026-10-04', 'Asia/Ulaanbaatar');

        $this->assertSame('2026-10-05', $this->service->parse('ирэх даваа', $sunday)['date']);
        $this->assertSame('2026-10-11', $this->service->parse('ирэх ням', $sunday)['date']);
        $this->assertSame('2026-10-11', $this->service->parse('ням гараг', $sunday)['date']);
    }

    // $now өгөөгүй үед Улаанбаатарын цагаар бодно. UTC 17:00 = УБ 01:00 (дараа өдөр)
    public function test_uses_ulaanbaatar_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 17:00', 'UTC'));

        try {
            $this->assertSame('2026-10-04', $this->service->parse('өнөөдөр')['date']);
            $this->assertSame('2026-10-05', $this->service->parse('маргааш')['date']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public static function noDate(): array
    {
        return [
            ['сайн байна уу'],
            ['ном байна уу'],   // ном → nm = ням, гэхдээ "гараг"-гүй
            ['ням'],            // ганцаараа хоёрдмол утгатай
            ['долоо хоног'],    // "ирэх/дараа"-гүй
        ];
    }

    #[DataProvider('noDate')]
    public function test_returns_null_without_date(string $text): void
    {
        $this->assertNull($this->service->parse($text, $this->saturday()));
    }
}
