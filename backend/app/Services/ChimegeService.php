<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Chimege (api.chimege.com) — монгол яриаг текст болгоно.
// Аудио: WAV, 16 kHz, mono, 16-bit. Body нь JSON биш, файлын түүхий bytes.
class ChimegeService
{
    // WAV bytes → "цалин дутуу байна"
    public function transcribe(string $wav): string
    {
        // Chimege-ийн албан ёсны жишээ: octet-stream + Punctuate (цэг, таслал нэмнэ)
        $response = $this->http()
            ->withHeaders(['Punctuate' => 'true'])
            ->withBody($wav, 'application/octet-stream')
            ->post('transcribe');

        if ($response->failed()) {
            throw new \RuntimeException("Chimege алдаа {$response->status()}: {$response->body()}");
        }

        // Хариу нь { "transcription": "..." } эсвэл шууд текст байж болно
        return trim($response->json('transcription') ?? $response->body());
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.chimege.url'))
            ->withHeaders(['Token' => config('services.chimege.token')])
            ->timeout(35);
    }
}
