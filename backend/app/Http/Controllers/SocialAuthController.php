<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

    /**
     * Гадны үйлчилгээгээр (Google) нэвтрэх.
     *
     * FE Google-ээс access_token авч ирүүлнэ → энд шалгаад манай
     * Passport token-оор солиж өгнө. Хариу нь /login-той ижил { user, token }.
     */
class SocialAuthController extends Controller
{
    /**
     * google — POST /api/auth/google  { access_token }
     *
     * 1. Токен МАНАЙ client_id-д олгогдсон эсэхийг шалгана (aud).
     *    Үүнгүйгээр өөр апп-д олгогдсон токеноор манайд нэвтэрч болно.
     * 2. Socialite-ээр Google-ээс хэрэглэгчийн мэдээлэл авна.
     * 3. google_id → email дарааллаар хайж, олдохгүй бол шинээр үүсгэнэ.
     */
    public function google(Request $request)
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
        ]);

        $tokenInfo = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'access_token' => $validated['access_token'],
        ]);

        if ($tokenInfo->failed() || $tokenInfo->json('aud') !== config('services.google.client_id')) {
            Log::warning('Google токен хүчингүй эсвэл өөр апп-ынх.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Google токен хүчингүй байна.'], 401);
        }

        // stateless() — API-д session байхгүй тул заавал.
        $googleUser = Socialite::driver('google')->stateless()->userFromToken($validated['access_token']);

        // Баталгаажаагүй Google email-ээр бусдын бүртгэлд холбогдохоос хамгаална.
        if (! ($googleUser->user['email_verified'] ?? false)) {
            return response()->json(['message' => 'Google и-мэйл баталгаажаагүй байна.'], 403);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Нууц үгээр бүртгэлтэй байсан бол Google-ийг нь холбоно.
            $user->google_id ??= $googleUser->getId();
            $user->save();
        } else {
            $user = User::create([
                'name'       => $googleUser->getName() ?? $googleUser->getEmail(),
                'email'      => $googleUser->getEmail(),
                'google_id'  => $googleUser->getId(),
                'company_id' => config('services.google.default_company_id') ?? Company::value('id'),
            ]);

            Log::info('Google-ээр шинэ хэрэглэгч бүртгэгдлээ.', ['user_id' => $user->id]);
        }

        // Google email-ийг баталгаажуулсан тул манай мэйл баталгаажуулалт хэрэггүй.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // AuthController::login-той ижил scope-ийн дүрэм.
        $scopes = $user->role === 'admin'
            ? ['books:manage', 'catalog:manage', 'loans:manage']
            : [];

        $token = $user->createToken('auth_token', $scopes)->accessToken;

        Log::info('Хэрэглэгч Google-ээр нэвтэрлээ.', ['user_id' => $user->id]);

        return response()->json([
            'user'  => $user->load('company'),
            'token' => $token,
        ]);
    }
}
