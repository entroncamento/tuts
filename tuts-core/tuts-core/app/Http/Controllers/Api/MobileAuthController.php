<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Demasiadas tentativas de login. Por favor tente novamente em {$seconds} segundos."],
            ]);
        }

        if (!Auth::once($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->blocked_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['A sua conta foi bloqueada ou desativada pela administração.'],
            ]);
        }

        $deviceName = $request->input('device_name') ?: ($request->header('X-Tuts-Client') ?: 'mobile-device');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'user'   => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'onboarding_completed_at' => $user->onboarding_completed_at,
            ],
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
                'ends_with:@ua.pt',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'prohibited',
            'professor_key' => 'prohibited',
            'device_name' => 'nullable|string|max:255',
        ]);

        $role = $this->registrationRoleForEmail($validated['email']);

        Log::info('[TUTS][AuthMobileRegister] resolved registration role', [
            'role' => $role,
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $deviceName = $request->input('device_name') ?: ($request->header('X-Tuts-Client') ?: 'mobile-device');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'message' => 'Conta criada com sucesso.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'onboarding_completed_at' => $user->onboarding_completed_at,
            ],
            'token' => $token,
        ], 201);
    }

    public function me(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'onboarding_completed_at' => $user->onboarding_completed_at,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'status' => 'sucesso',
            'message' => 'Sessão móvel terminada com sucesso.'
        ]);
    }

    private function registrationRoleForEmail(string $email): string
    {
        return $this->isTeacherEmail($email) ? 'professor' : 'aluno';
    }

    private function isTeacherEmail(string $email): bool
    {
        $normalizedEmail = Str::lower(trim($email));
        $whitelist = config('services.api_registration.teacher_email_whitelist', []);

        if (!is_array($whitelist)) {
            $whitelist = explode(',', (string) $whitelist);
        }

        foreach ($whitelist as $teacherEmail) {
            $normalizedTeacherEmail = Str::lower(trim((string) $teacherEmail));

            if ($normalizedTeacherEmail !== '' && hash_equals($normalizedTeacherEmail, $normalizedEmail)) {
                return true;
            }
        }

        return false;
    }
}
