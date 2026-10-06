<?php

namespace Tests\Unit;

use App\Services\DepartmentService;
use App\Services\ElasticDepartmentService;
use App\Services\WordShiftService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Жинхэнэ Elasticsearch (ELASTIC_HOST, анхдагч localhost:9200) дээр ажиллана.
// ES асаагүй бол тестүүд алгасагдана (skipped) — бусад тестэд саад болохгүй.
// Хөгжүүлэлтийн "departments" индексэд хүрэхгүй, тусдаа "departments_test" ашиглана.
class ElasticDepartmentServiceTest extends TestCase
{
    private const INDEX = 'departments_test';

    private const DEPARTMENTS = [
        'Санхүү'         => ['цалин'],
        'Хүний нөөц'     => ['хоцор', 'чөлөө', 'ирц', 'халагд'],
        'IT'             => ['компьютер', 'гацсан', 'wifi'],
        'Аюулгүй байдал' => ['хаалга', 'камер'],
    ];

    private static ?ElasticDepartmentService $service = null;

    // Индексийг бүх тестэд нэг л удаа үүсгэнэ (~1 сек)
    public static function setUpBeforeClass(): void
    {
        self::bootLaravel();

        $service = new ElasticDepartmentService(self::INDEX);
        if (! $service->ping()) {
            return;
        }

        $service->createIndex();
        $service->indexDepartments(self::DEPARTMENTS);
        self::$service = $service;
    }

    public static function tearDownAfterClass(): void
    {
        self::$service?->deleteIndex();
        self::$service = null;

        Facade::clearResolvedInstances();
        Container::setInstance(null);
    }

    protected function setUp(): void
    {
        if (self::$service === null) {
            $this->markTestSkipped('Elasticsearch асаагүй байна (docker start elasticsearch)');
        }
    }

    public static function messages(): array
    {
        return [
            ['Миний цалин дутуу байна', 'Санхүү'],
            ['tslin dtuu bn', 'Санхүү'],
            ['Миний ЦАЛИНГАА ирээгүй', 'Санхүү'],      // том үсэг
            ['TSALLIIN irsengui', 'Санхүү'],           // том латин + давхар үсэг
            ['Би өнөөдөр хоцорлоо', 'Хүний нөөц'],
            ['4uluu avmaar bn', 'Хүний нөөц'],
            ['ирцээ бүртгүүлж чадсангүй', 'Хүний нөөц'],
            ['komputer gatssan', 'IT'],
            ['Computer ajillahgui', 'IT'],             // 1 үсгийн алдаа (fuzzy)
            ['wifi ajillahgui', 'IT'],
            ['kamer ajillahgui', 'Аюулгүй байдал'],
        ];
    }

    #[DataProvider('messages')]
    public function test_detects_department(string $text, string $expected): void
    {
        $this->assertSame($expected, self::$service->detect($text)['department']);
    }

    // ES-ийн analyzer нь PHP-ийн WordShiftService-тэй яг ижил үр дүн өгөх ёстой
    #[DataProvider('messages')]
    public function test_matches_php_version(string $text): void
    {
        $words = new WordShiftService(array_merge(...array_values(self::DEPARTMENTS)));
        $php   = new DepartmentService($words, self::DEPARTMENTS);

        $result = self::$service->detect($text);

        $this->assertSame($php->detect($text)['department'], $result['department']);
        $this->assertEquals($words->shiftText($text), $result['words']);
    }

    public function test_returns_corrected_words(): void
    {
        $this->assertSame(['tslin' => 'цалин'], self::$service->detect('tslin dtuu bn')['words']);
        $this->assertEquals(
            ['komputer' => 'компьютер', 'gatssan' => 'гацсан'],
            self::$service->detect('komputer gatssan!!')['words']
        );
    }

    public function test_unknown_text_returns_null(): void
    {
        $result = self::$service->detect('сайн байна уу');

        $this->assertNull($result['department']);
        $this->assertSame([], $result['words']);
    }

    // Санхүү 1, IT 1 — аль нь гэдгийг таах биш, хэрэглэгчээс асуух ёстой
    public function test_tie_returns_null(): void
    {
        $result = self::$service->detect('tsalin wifi');

        $this->assertNull($result['department']);
        $this->assertSame(1, $result['scores']['Санхүү']);
        $this->assertSame(1, $result['scores']['IT']);
    }

    // 2 үсэгтэй skeleton зөвхөн яг таарна: "уучлаарай" (qlr) → чөлөө (ql) биш
    public function test_short_skeleton_matches_only_exactly(): void
    {
        $this->assertSame([], self::$service->detect('уучлаарай')['words']);
    }

    // "халагдсан" нь хаалга (hlg), халагд (hlgd) хоёуланд prefix-ээр таарна — урт нь ялна
    public function test_longest_skeleton_wins(): void
    {
        $result = self::$service->detect('халагдсан');

        $this->assertSame(['халагдсан' => 'халагд'], $result['words']);
        $this->assertSame('Хүний нөөц', $result['department']);
    }

    // Laravel-ийг бүтнээр ачаалахгүй — service-д хэрэгтэй config() болон Http facade л
    private static function bootLaravel(): void
    {
        $app = new Container();
        $app->instance('config', new Repository([
            'services' => ['elastic' => ['host' => getenv('ELASTIC_HOST') ?: 'http://localhost:9200']],
        ]));
        $app->instance(Factory::class, new Factory());

        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }
}
