<?php

namespace Tests\Unit;

use App\Services\DepartmentService;
use App\Services\WordShiftService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DepartmentServiceTest extends TestCase
{
    private DepartmentService $service;

    private const DEPARTMENTS = [
        'Санхүү'     => ['цалин'],
        'Хүний нөөц' => ['хоцор', 'чөлөө', 'ирц'],
        'IT'         => ['компьютер', 'гацсан', 'wifi'],
    ];

    protected function setUp(): void
    {
        $this->service = new DepartmentService(
            new WordShiftService(array_merge(...array_values(self::DEPARTMENTS))),
            self::DEPARTMENTS
         );
    }

    public static function messages(): array
    {
        return [
            ['Миний цалин дутуу байна', 'Санхүү'],
            ['tslin dtuu bn', 'Санхүү'],
            ['tsalen ireegui', 'Санхүү'],
            ['Би өнөөдөр хоцорлоо', 'Хүний нөөц'],
            ['4uluu avmaar bn', 'Хүний нөөц'],
            ['ирцээ бүртгүүлж чадсангүй', 'Хүний нөөц'],
            ['komputer gatssan', 'IT'],
            ['wifi ajillahgui', 'IT'],
        ];
    }

    #[DataProvider('messages')]
    public function test_detects_department(string $text, string $expected): void
    {
        $this->assertSame($expected, $this->service->detect($text)['department']);
    }

    public function test_unknown_text_returns_null(): void
    {
        $this->assertNull($this->service->detect('сайн байна уу')['department']);
    }

    // Санхүү 1, IT 1 — аль нь гэдгийг таах биш, хэрэглэгчээс асуух ёстой
    public function test_tie_returns_null(): void
    {
        $result = $this->service->detect('tsalin wifi');

        $this->assertNull($result['department']);
        $this->assertSame(1, $result['scores']['Санхүү']);
        $this->assertSame(1, $result['scores']['IT']);
    }
}
