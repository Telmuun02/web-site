<?php

namespace App\Services;

// Anthropic-ийн `tools` параметрт илгээх хэрэгслүүдийн тодорхойлолт.
// PHP массив — Http::post() өөрөө JSON болгоно.
// description-ыг Claude уншдаг: хэзээ дуудахаа үүнээс шийднэ.
// name нь ChatController-ийн match() дахь нэртэй таарах ёстой.
class ToolDefinitions
{
    public static function all(): array
    {
        return [
            [
                'name'        => 'get_books',
                'description' => 'Номын сангийн бүх номын жагсаалт, үлдэгдлийн хамт. Ямар ном байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_category',
                'description' => 'Номын ангиллуудын жагсаалт (Fiction, Science, Technology г.м.). Ямар ангилал байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_authors',
                'description' => 'Номын сангийн зохиолчдын жагсаалт. Ямар зохиолчийн ном байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
            [
                'name'        => 'get_company',
                'description' => 'Системд бүртгэлтэй байгууллага (компани)-уудын жагсаалт. Ямар байгууллагууд байгааг асуухад ашигла.',
                'input_schema' => self::noInput(),
            ],
        ];
    }

    // Оролтын параметргүй хэрэгслийн schema. [] биш stdClass: PHP-ийн хоосон
    // массив JSON-д [] (жагсаалт) болно, schema {} (объект) шаарддаг.
    private static function noInput(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
        ];
    }
}
