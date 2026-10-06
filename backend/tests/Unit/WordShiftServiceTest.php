<?php

namespace Tests\Unit;

use App\Services\WordShiftService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WordShiftServiceTest extends TestCase
{
    private WordShiftService $service;

    // Логикийг шалгана, бодит толь бичгийг биш — тестэд хэрэгтэй үгс л байхад хангалттай
    private const KEYWORDS = [
        'цалин', 'хоцор', 'чөлөө', 'ирц', 'нууц', 'компьютер', 'гацсан', 'wifi',
        'имэйл', 'интернет', 'принтер', 'томилолт', 'ажилд', 'халагд', 'тасал',
    ];

    protected function setUp(): void
    {
        $this->service = new WordShiftService(self::KEYWORDS);
    }

    // Кирилл, латин, эгшиггүй, буруу эгшигтэй бичлэг бүгд нэг араг яс болно
    public function test_skeleton_unifies_spellings(): void
    {
        foreach (['цалин', 'tsalin', 'tslin', 'tsalen', 'celen', 'cln', 'tsalliin'] as $word) {
            $this->assertSame('cln', $this->service->skeleton($word), $word);
        }
    }

    public static function shiftCases(): array
    {
        return [
            // эгшиг хасагдана
            ['cln', 'цалин'],
            ['tslin', 'цалин'],
            ['tsalen', 'цалин'],
            ['TSALIN', 'цалин'],
            // нөхцөлтэй хэлбэр — prefix таарц
            ['цалингаа', 'цалин'],
            ['хоцорлоо', 'хоцор'],
            ['hotsorloo', 'хоцор'],
            ['xocrson', 'хоцор'],
            // ч → ch / 4
            ['chuluu', 'чөлөө'],
            ['4uluu', 'чөлөө'],
            // богино араг яс (2 үсэг)
            ['ирцээ', 'ирц'],
            ['nuuts', 'нууц'],
            // латин бичлэг
            ['computer', 'компьютер'],
            ['gatssan', 'гацсан'],
            ['wifi', 'wifi'],
            ['imail', 'имэйл'],
            ['internet', 'интернет'],
            ['printer', 'принтер'],
            ['tomillt', 'томилолт'],
            ['ajild', 'ажилд'],
            ['halagdsan', 'халагд'],
            ['tasalsan', 'тасал'],
        ];
    }

    #[DataProvider('shiftCases')]
    public function test_shift_finds_keyword(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->service->shift($input));
    }

    public static function unknownWords(): array
    {
        return [
            ['bn'],       // байна
            ['dtuu'],     // дутуу — толь бичигт байхгүй
            ['sain'],
            ['a'],        // араг ясгүй
            [''],
            ['qlmnbr'],   // "ql" (чөлөө)-өөр эхэлсэн ч хэт урт — богино араг ясны хамгаалалт
        ];
    }

    #[DataProvider('unknownWords')]
    public function test_shift_returns_null_for_unknown(string $input): void
    {
        $this->assertNull($this->service->shift($input));
    }

    public function test_shift_text_returns_only_recognized_words(): void
    {
        $this->assertSame(['tslin' => 'цалин'], $this->service->shiftText('tslin dtuu bn'));
        $this->assertSame(
            ['komputer' => 'компьютер', 'gatssan' => 'гацсан'],
            $this->service->shiftText('komputer gatssan!!')
        );
        $this->assertSame([], $this->service->shiftText('сайн байна уу'));
    }
}
