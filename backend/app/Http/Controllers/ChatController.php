<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Services\ToolDefinitions;
use App\Services\ChatTools;

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

    public function send(Request $request)
    {
        $systemprompt = "Та бол номын туслах чат бот.
         Хэрэглэгчийн асуултанд зөв, товч, эелдэгээр хариулна уу. 
         Хэрэглэгчийн асуултанд шууд хариулахад хангалттай бөгөөд илүү дэлгэрэнгүй мэдээлэл өгөх шаардлагагүй.
         
         Дүрэм: 
         1. Зөвхөн монгол буюу кирилл үсгээр хариулт өг
         2. Хариулт 100 тэмдэгтээс хэтрэхгүй байх ёстой 
         3. Манай номын сангийн системтэй холбоотой асуултанд хариулт өгөх, бусад сэдвээр хариулахгүй байх
         4. Хэрэглэгчийн асуултанд шууд хариулах";

        $request->validate([
            'message' => 'required|string|max:100',
        ]);

        $history = session('chat_history', []);

        // Хэрэглэгчийн шинэ мессежийг түүхэнд нэмэх
        $history[] = [
            'role' => 'user',
            'content' => $request->input('message'),
        ];

        // Claude API руу хүсэлт илгээх
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1000,
            'system' => $systemprompt,
            'messages' => $history,
            'tools' => ToolDefinitions::all(),
        ]);

        // Хүсэлт амжилтгүй бол алдаа буцаах
        if ($response->failed()) {
            return response()->json([
                'error' => 'Claude API-тай холбогдоход алдаа гарлаа.',
                'details' => $response->json(),
            ], $response->status());
        }

        $data = $response->json();  

        if ($data['stop_reason'] === 'tool_use') {    // ← ЭНД нэмнэ
            $toolUse = collect($data['content'])->firstWhere('type', 'tool_use');

            $tools = new ChatTools();

            $result = match ($toolUse['name']) {
                'get_books' => $tools->getBooks(),
                'get_category' => $tools->getCategory(),
                'get_authors' => $tools->getAuthors(),
                'get_company' => $tools->getCompany(),
            };

            if ($toolUse['input'] === []) {
                $toolUse['input'] = new \stdClass();
            }

            $history[] = ['role' => 'assistant', 'content' => [$toolUse]];

            $history[] = ['role' => 'user', 'content' => [[
                'type'        => 'tool_result',
                'tool_use_id' => $toolUse['id'],
                'content'     => $result->toJson(),
            ]]];

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 1000,
                'system' => $systemprompt,
                'messages' => $history,
                'tools' => ToolDefinitions::all(),
            ]);

            $data = $response->json();  
            
        }

        $reply = collect($data['content'] ?? [])
            ->firstWhere('type', 'text')['text'] ?? '';

        // Claude-ийн хариуг мөн түүхэнд нэмэх
        $history[] = [
            'role' => 'assistant',
            'content' => $reply,
        ];

        $sessionId = session()->getId();

        session(['chat_history' => $history]);

        cookie(
            'chat_session_id',  
            $sessionId,
            120,
            '/',
        );

        return response()->json([
            'reply' => $reply,
        ]);
    }

    public function reset(Request $request)
    {
        session()->forget('chat_history');

        return response()->json([
            'message' => 'Chat history reset successfully.',
        ]);
    }   
}