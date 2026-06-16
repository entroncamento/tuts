<?php

use App\Models\TutsNotification;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tuts:notify-test {--user= : ID ou email do utilizador} {--type=system : reminder, system, study, chat, rag, success, warning ou error} {--force : Permite executar em producao}', function () {
    if (app()->isProduction() && ! $this->option('force')) {
        $this->error('Este comando cria notificacoes de teste. Usa --force para executar em producao.');

        return Command::FAILURE;
    }

    $userRef = $this->option('user');

    $user = User::query()
        ->when($userRef, function ($query) use ($userRef) {
            $query->where(function ($innerQuery) use ($userRef) {
                if (ctype_digit((string) $userRef)) {
                    $innerQuery->whereKey((int) $userRef);
                }

                $innerQuery->orWhere('email', $userRef);
            });
        }, function ($query) {
            $query->latest('id');
        })
        ->first();

    if (! $user) {
        $this->error('Nenhum utilizador encontrado para receber a notificacao.');

        return Command::FAILURE;
    }

    $type = TutsNotification::normalizeType($this->option('type'));
    $meta = TutsNotification::visualMetaFor($type);

    $notification = TutsNotification::create([
        'user_id' => $user->id,
        'type' => $type,
        'title' => 'Notificacao de teste TUTS',
        'body' => 'Esta notificacao confirma que o sistema esta a receber, listar e marcar eventos corretamente.',
        'data' => [
            'url' => '/notificacoes',
            'icon' => $meta['icon'],
            'tone' => $meta['tone'],
            'source' => 'artisan:tuts:notify-test',
        ],
    ]);

    $this->info("Notificacao #{$notification->id} criada para {$user->email}.");

    return Command::SUCCESS;
})->purpose('Cria uma notificacao de teste segura para desenvolvimento.');
