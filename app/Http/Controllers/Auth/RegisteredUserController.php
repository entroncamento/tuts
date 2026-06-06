<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:aluno,professor',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
                'ends_with:@ua.pt', // Nativo do Laravel: Garante domínio institucional
            ],
            'professor_key' => [
                $request->role === 'professor' ? 'required' : 'nullable',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->role === 'professor') {
                        // Idealmente, usar config('services.rag.professor_api_key') em produção
                        $expectedKey = env('PROFESSOR_API_KEY');

                        // Blindagem contra Timing Attacks usando hash_equals
                        if (!$expectedKey || !hash_equals($expectedKey, (string) $value)) {
                            $fail('A chave de professor é inválida ou expirou.');
                        }
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        // Dispara o evento que vai enviar o email de verificação automaticamente
        event(new Registered($user));

        Auth::login($user);

        // O middleware 'verified' (que deves usar nas rotas web) vai impedir
        // que eles passem da rota home sem clicar no link do email!
        return redirect()->route('home');
    }
}
