<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->orderBy('name', 'asc')
            ->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::withCount(['chats', 'reminders', 'calendarItems', 'personalMaterials'])
            ->findOrFail($id);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:student,aluno,teacher,professor,admin,super_admin',
        ]);

        // Normalize teacher/student role strings to match backend's expected roles if any
        // Since prompt says backend seems to use aluno/professor, but frontend student/teacher:
        // We will map student->aluno, teacher->professor if needed. Let's see what users table actually has.
        // Let's write the value directly as requested, but keeping compatibility.

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        $user->email_verified_at = now();
        $user->save();

        AuditLogService::log(
            'user_created',
            User::class,
            $user->id,
            ['email' => $user->email, 'role' => $user->role]
        );

        return response()->json([
            'message' => 'Utilizador criado com sucesso.',
            'user' => $user
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        $oldEmail = $user->email;
        $user->update($validated);

        AuditLogService::log(
            'user_updated',
            User::class,
            $user->id,
            ['old_email' => $oldEmail, 'new_email' => $user->email]
        );

        return response()->json([
            'message' => 'Utilizador atualizado com sucesso.',
            'user' => $user
        ]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();

        $validated = $request->validate([
            'role' => 'required|string|in:student,aluno,teacher,professor,admin,super_admin',
        ]);

        $newRole = $validated['role'];
        $oldRole = $user->role;

        if ($oldRole === $newRole) {
            return response()->json(['message' => 'O utilizador já possui este cargo.']);
        }

        // Integrity check: prevent downgrading the last super_admin
        if ($oldRole === 'super_admin' && $newRole !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return response()->json([
                    'message' => 'Não é possível alterar o cargo do único Super Administrador do sistema.'
                ], 422);
            }
        }

        $user->role = $newRole;
        $user->save();

        AuditLogService::log(
            'user_role_changed',
            User::class,
            $user->id,
            ['old_role' => $oldRole, 'new_role' => $newRole]
        );

        return response()->json([
            'message' => "Cargo de {$user->name} alterado para {$newRole}.",
            'user' => $user
        ]);
    }

    public function toggleBlock(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            return response()->json([
                'message' => 'Não pode bloquear ou desbloquear a sua própria conta.'
            ], 422);
        }

        // Integrity check: prevent blocking the last super_admin
        if ($user->role === 'super_admin' && !$user->blocked_at) {
            $activeSuperAdminCount = User::where('role', 'super_admin')->whereNull('blocked_at')->count();
            if ($activeSuperAdminCount <= 1) {
                return response()->json([
                    'message' => 'Não é possível bloquear o único Super Administrador ativo do sistema.'
                ], 422);
            }
        }

        if ($user->blocked_at) {
            $user->blocked_at = null;
            $action = 'user_unblocked';
            $message = "Utilizador {$user->name} desbloqueado com sucesso.";
        } else {
            $user->blocked_at = now();
            $action = 'user_blocked';
            $message = "Utilizador {$user->name} bloqueado com sucesso.";
        }

        $user->save();

        AuditLogService::log($action, User::class, $user->id);

        return response()->json([
            'message' => $message,
            'user' => $user
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            return response()->json([
                'message' => 'Não pode remover ou desativar a sua própria conta.'
            ], 422);
        }

        if ($user->role === 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return response()->json([
                    'message' => 'Não é possível remover ou desativar o único Super Administrador do sistema.'
                ], 422);
            }
        }

        // Safe soft-delete / deactivation behavior: we set blocked_at to now
        $user->blocked_at = now();
        $user->save();

        AuditLogService::log('user_deactivated', User::class, $user->id);

        return response()->json([
            'message' => "Utilizador desativado/bloqueado com sucesso (soft-delete)."
        ]);
    }
}
