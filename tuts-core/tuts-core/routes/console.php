<?php

use App\Models\TutsNotification;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Illuminate\Support\Facades\DB;

Artisan::command('tuts:db-fingerprint {--label=manual}', function () {
    $label = $this->option('label');

    $this->info("[TUTS][DB-FINGERPRINT][{$label}] Starting");

    try {
        $default = config('database.default');
        $connection = config("database.connections.{$default}", []);

        $safeConfig = [
            'default' => $default,
            'host' => $connection['host'] ?? null,
            'port' => $connection['port'] ?? null,
            'database' => $connection['database'] ?? null,
            'username' => $connection['username'] ?? null,
        ];

        $this->info('[TUTS][DB-FINGERPRINT]['.$label.'] config='.json_encode($safeConfig));

        $dbInfo = DB::selectOne('select current_database() as database, current_schema() as schema, current_user as "user"');

        $this->info('[TUTS][DB-FINGERPRINT]['.$label.'] current='.json_encode($dbInfo));

        $tables = [
            'migrations',
            'users',
            'courses',
            'subjects',
            'subject_user',
            'subject_sections',
            'subject_materials',
            'chats',
            'messages',
            'student_message_analyses',
        ];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->info("[TUTS][DB-FINGERPRINT][{$label}] {$table}={$count}");
            } catch (\Throwable $e) {
                $this->warn("[TUTS][DB-FINGERPRINT][{$label}] {$table}=ERROR ".$e->getMessage());
            }
        }
    } catch (\Throwable $e) {
        $this->error('[TUTS][DB-FINGERPRINT]['.$label.'] FAILED '.$e->getMessage());
        return self::FAILURE;
    }

    $this->info("[TUTS][DB-FINGERPRINT][{$label}] Done");

    return self::SUCCESS;
})->purpose('Print safe DB connection fingerprint and row counts.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tuts:seed-student-dashboard-demo', function () {
    $this->info('Starting Student Dashboard Demo Seeder...');
    $seeder = new \Database\Seeders\StudentDashboardDemoSeeder();
    $seeder->setCommand($this);
    $seeder->run();
    $this->info('All done!');
})->purpose('Seed fake student activity data for Redes de Computadores to test the teacher dashboard.');

Artisan::command('tuts:rag-reingest-demo', function (\App\Services\RagIngestionService $ragIngestion) {
    $this->info('Starting RAG Ingestion for all official PDF materials...');
    
    $materials = \App\Models\SubjectMaterial::with('subject')->get();
    $total = $materials->count();
    $success = 0;
    $failed = 0;
    $skipped = 0;
    
    foreach ($materials as $index => $material) {
        $num = $index + 1;
        $this->info("[{$num}/{$total}] Processing: {$material->name} (Subject: {$material->subject->acronym})");
        
        $mimeType = strtolower((string) $material->mime_type);
        $extension = strtolower((string) pathinfo($material->path ?: $material->url ?: $material->name, PATHINFO_EXTENSION));
        $type = strtolower((string) $material->type);

        $isPdf = $mimeType === 'application/pdf' || $extension === 'pdf' || $type === 'pdf';
        
        if (!$isPdf) {
            $this->comment("  Skipped: not a PDF.");
            $skipped++;
            continue;
        }
        
        try {
            $result = $ragIngestion->ingestSubjectMaterial($material);
            if (isset($result['status']) && $result['status'] === 'success') {
                $this->info("  Success! Chunks created or updated.");
                $success++;
            } else {
                $this->error("  Failed: " . ($result['reason'] ?? $result['message'] ?? 'unknown_reason'));
                $failed++;
            }
        } catch (\Exception $e) {
            $this->error("  Error occurred: " . $e->getMessage());
            $failed++;
        }
    }
    
    $this->info("\nAll completed! Success: {$success}, Failed: {$failed}, Skipped: {$skipped}");
})->purpose('Reingest all seeded official PDF materials into RAG');


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
