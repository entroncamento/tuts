<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Aqui devem ficar apenas as rotas stateless (ex: App Mobile, Terceiros).
| As rotas da aplicação Web (Vue/Inertia) que usam cookies de sessão 
| estão definidas no routes/web.php com o prefixo /api/.
*/

// Deixamos apenas a rota padrão do Sanctum comentada para referência futura
// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });