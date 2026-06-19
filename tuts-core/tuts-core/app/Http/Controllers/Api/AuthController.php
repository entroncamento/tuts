<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    /**
     * Registo API — cria apenas contas de aluno e envia email de verificação.
     * Não inicia sessão; o frontend deve encaminhar para login após verificação.
     */
    public function register(Request $request)
    {
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
        ]);

        $autoVerify = (bool) config('services.api_registration.auto_verify', false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'aluno',
        ]);

        if ($autoVerify) {
            // Demo/dev/staging unblock only: enable while real email delivery is unavailable.
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        } else {
            event(new Registered($user));
        }

        return response()->json([
            'status' => 'sucesso',
            'message' => $autoVerify
                ? 'Conta criada com sucesso. Já podes iniciar sessão.'
                : 'Conta criada com sucesso. Verifica o teu email antes de iniciar sessão.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * Login — autentica o utilizador e inicia a sessão Sanctum.
     * O browser guarda o cookie de sessão automaticamente.
     */
    public function login(Request $request)
    {
        // 1. Limites nas validações (Prevenção contra payloads gigantes)
        $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'remember' => 'nullable|boolean', // O utilizador é que decide se quer sessão contínua
        ]);

        // 2. Defesa contra Força Bruta (Throttling por Email + IP)
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Demasiadas tentativas de login. Por favor tente novamente em {$seconds} segundos."],
            ]);
        }

        // 3. Autenticação sem "Remember Me" forçado
        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            // Regista a falha no RateLimiter
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                // Ortografia atualizada (incorretas vs incorrectas) e genérica
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // O utilizador acertou, limpamos o contador de tentativas falhadas
        RateLimiter::clear($throttleKey);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 4. Barreira de Verificação de Email (Alinhado com a nossa mudança no User.php)
        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => ['A sua conta ainda não foi verificada. Por favor, verifique a sua caixa de correio institucional (@ua.pt).'],
            ]);
        }

        // 5. Prevenção contra Session Fixation
        $request->session()->regenerate();

        return response()->json([
            'status' => 'sucesso',
            // Substituímos o ->only() por um array associativo explícito. 
            // O IDE adora isto e evita o erro P1013.
            'user'   => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * Logout — invalida a sessão atual.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'sucesso']);
    }

    /**
     * Devolve os dados do utilizador autenticado.
     * Útil para o frontend verificar se a sessão ainda é válida.
     */
    public function me(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            // Usamos a mesma abordagem explícita aqui
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }
}
