<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Services\ToolDefinitions;
use App\Services\ChatTools;
use App\Services\DepartmentService;
use App\Services\ChimegeService;
use Illuminate\Support\Facades\Log;

/**
 * Чат үйлдлүүд.
 *
 */
class ChatController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Chat API is working.',
        ]);
    }

    public function send(Request $request, DepartmentService $departments)
    {
        $request->validate([
            'message' => 'required|string|max:100',
            'chat_id' => 'nullable|uuid',
        ]);

        return response()->json($this->reply($request, $departments, $request->input('message')));
    }

    // Дуу → текст: WAV (16 kHz, mono) → Chimege → { text }.
    // Чат руу илгээхгүй — frontend текстийг input-д тавьж, хэрэглэгч шалгаад send()-ээр илгээнэ.
    public function transcribe(Request $request, ChimegeService $chimege)
    {
        $request->validate([
            // 60 сек × 16000 × 2 byte ≈ 1.9 MB
            'audio'   => 'required|file|mimes:wav|max:2048',
            'chat_id' => 'nullable|uuid',
        ]);

        try {
            $text = $chimege->transcribe($request->file('audio')->get());
        } catch (\Throwable $e) {
            Log::warning('Chimege танилт амжилтгүй', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Дуу таних үйлчилгээ хариу өгсөнгүй. Дахин оролдоно уу.'], 502);
        }

        if ($text === '') {
            return response()->json(['message' => 'Яриа танигдсангүй. Дахин, тод хэлээд үзээрэй.'], 422);
        }

        return response()->json(['text' => $text]);
    }

    // Мессежид хариулах: хэлтэс → Claude (tool-уудтай) → түүх хадгалах.
    private function reply(Request $request, DepartmentService $departments, string $message): array
    {
        $systemprompt = <<<PROMPT
        Та Folio номын сангийн туслах. Монгол хэлээр, зөв бичгийн дүрмийн дагуу, энгийн бөгөөд богино өгүүлбэрээр, эелдэг хариулна.

        Дүрэм:
        1. Хариултаа монгол хэлээр бич. Номын нэр, зохиолчийн нэр, ангиллын нэрийг орчуулахгүй, кирилл болгохгүй, tool-оос ирсэн хэлбэрээр нь яг хуулж бич.
        2. Хариулт 2-3 өгүүлбэрээс хэтрэхгүй. Олон ном жагсаах бол хамгийн ихдээ 3-ыг нэрлэ.
        3. Markdown (**, #, -, *) бүү ашигла. Энгийн текстээр бич. Жагсаалт хэрэгтэй бол мөр бүрийг "1.", "2." гэж эхлүүл.
        4. Зөвхөн манай номын сангийн талаарх асуултад хариул. Өөр сэдвээр асуувал эелдгээр татгалз.
        5. Ном, зээл, тоо хэмжээний мэдээллийг зөвхөн tool-оос ав. Tool-д байхгүй зүйлийг бүү зохио, "мэдээлэл олдсонгүй" гэж хэл.
        6. Хэрэглэгч нэрээ хэлсэн бол түүгээр нь дууд.

        Жишээ:
        Асуулт: Шинжлэх ухааны ном байна уу?
        Хариулт: Тийм, байна. "The Selfish Gene" (Richard Dawkins) номын 4 хувь одоо байгаа.

        Асуулт: Миний зээлсэн ном хэзээ дуусах вэ?
        Хариулт: Таны "Sapiens" номын буцаах хугацаа 2026-08-13 байсан бөгөөд хугацаа нь хэтэрсэн байна. Аль болох хурдан буцаана уу.

        Асуулт: Өнөөдөр цаг агаар ямар байна?
        Хариулт: Уучлаарай, би зөвхөн номын сангийн талаар туслах боломжтой.
        PROMPT;

        $key = $this->chatKey($request);

        // Мессеж аль хэлтэст хамаарахыг AI-гүйгээр тодорхойлоод Claude-д мэдэгдэнэ
        $department = $this->detectDepartment($departments, $message);

        if ($department['name']) {
            $systemprompt .= "\n\nХэрэглэгчийн энэ мессеж \"{$department['name']}\" хэлтэст хамаарах магадлалтай (түлхүүр үгээр тодорхойлсон).";
        }

        // $history — зөвхөн текст мессеж, Cache-д хадгалагдана.
        // $messages — Claude руу илгээх бүтэн жагсаалт, tool блокууд энд л нэмэгдэнэ.
        $history = $key ? Cache::get($key, []) : [];

        // Хэрэглэгчийн шинэ мессежийг түүхэнд нэмэх
        $history[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $messages = $history;

        // Claude API руу хүсэлт илгээх
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1000,
            'system' => $systemprompt,
            'messages' => $messages,
            'tools' => ToolDefinitions::all(),
        ]);

        // Хүсэлт амжилтгүй бол алдаа буцаах
        // reply() массив буцаадаг тул алдааны хариуг abort()-оор шууд илгээнэ
        if ($response->failed()) {
            abort(response()->json([
                'error' => 'Claude API-тай холбогдоход алдаа гарлаа.',
                'details' => $response->json(),
            ], $response->status()));
        }

        $data = $response->json();  

        if ($data['stop_reason'] === 'tool_use') {    // ← ЭНД нэмнэ
            $toolUse = collect($data['content'])->firstWhere('type', 'tool_use');

            $tools = new ChatTools(auth('api')->user());

            $input = $toolUse['input'];

            $result = match ($toolUse['name']) {
                'get_library_overview' => $tools->getLibraryOverview(),
                'get_books' => $tools->getBooks(),
                'get_category' => $tools->getCategory(),
                'get_authors' => $tools->getAuthors(),
                'get_company' => $tools->getCompany(),
                'search_books' => $tools->searchBooks($input),
                'get_book_details' => $tools->getBookDetails($input),
                'get_books_by_author' => $tools->getBooksByAuthor($input),
                'get_books_by_category' => $tools->getBooksByCategory($input),
                'get_available_books' => $tools->getAvailableBooks($input),
                'get_popular_books' => $tools->getPopularBooks($input),
                'get_loans' => $tools->getLoans($input),
                default => ['error' => 'Unknown tool: ' . $toolUse['name']],
            };

            if ($toolUse['input'] === []) {
                $toolUse['input'] = new \stdClass();
            }

            $messages[] = ['role' => 'assistant', 'content' => [$toolUse]];

            $messages[] = ['role' => 'user', 'content' => [[
                'type'        => 'tool_result',
                'tool_use_id' => $toolUse['id'],
                'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]]];

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 1000,
                'system' => $systemprompt,
                'messages' => $messages,
                'tools' => ToolDefinitions::all(),
            ]);

            $data = $response->json();
            
        }

        $reply = collect($data['content'] ?? [])
            ->firstWhere('type', 'text')['text'] ?? '';

        // Хоосон хариу хадгалбал дараагийн хүсэлтэд API алдаа өгнө — тиймээс
        // хариу байвал л user + assistant хосыг хамт хадгална.
        // Сүүлийн 10 (5 хос) — токен хэмнэнэ; тэгш тоо тул үргэлж user-ээр эхэлнэ.
        if ($key && $reply !== '') {
            $history[] = [
                'role' => 'assistant',
                'content' => $reply,
            ];

            Cache::put($key, array_slice($history, -10), now()->addHours(2));
        }

        // TODO(туршилт): хэлтсийг чатад харуулж байна — тест дууссаны дараа prefix-ийг хасна.
        // Cache-ийн түүхэнд ороогүй тул Claude-ын контекстэд нөлөөлөхгүй.
        $label = "[Хэлтэс: " . ($department['name'] ?? 'тодорхойгүй') . " · {$department['source']}]\n";

        return [
            'reply' => $label . $reply,
            'department' => $department,
        ];
    }

    // source нь чатын label-д харагдана — дараа Elasticsearch хувилбар нэмэхэд ялгахад хэрэгтэй
    private function detectDepartment(DepartmentService $departments, string $message): array
    {
        return ['name' => $departments->detect($message)['department'], 'source' => 'php'];
    }

    // Refresh хийсний дараа widget өмнөх мессежүүдээ харуулахад ашиглана.
    // Cache-д зөвхөн текст мессеж байгаа тул шууд буцаахад болно.
    public function history(Request $request)
    {
        $request->validate([
            'chat_id' => 'nullable|uuid',
        ]);

        $key = $this->chatKey($request);

        return response()->json([
            'messages' => $key ? Cache::get($key, []) : [],
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'chat_id' => 'nullable|uuid',
        ]);

        if ($key = $this->chatKey($request)) {
            Cache::forget($key);
        }

        return response()->json([
            'message' => 'Chat history reset successfully.',
        ]);
    }

    // Нэвтэрсэн бол user id, зочин бол frontend-ийн chat_id.
    // Аль нь ч байхгүй бол null — бүх зочин нэг түлхүүр хуваалцахаас сэргийлнэ.
    private function chatKey(Request $request): ?string
    {
        if ($user = auth('api')->user()) {
            return 'chat:user:' . $user->id;
        }

        $chatId = $request->input('chat_id');

        return $chatId ? 'chat:guest:' . $chatId : null;
    }
}