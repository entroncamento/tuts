<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login — autentica o utilizador e inicia a sessão Sanctum.
     * O browser guarda o cookie de sessão automaticamente.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), true)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais estão incorrectas.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'status' => 'sucesso',
            'user'   => $request->user()->only('id', 'name', 'email'),
        ]);
    }

    /**
     * Logout — invalida a sessão actual.
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
     * Útil para o Vue verificar se a sessão ainda é válida ao carregar a app.
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->only('id', 'name', 'email'),
        ]);
    }
}
