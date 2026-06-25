<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚜 A importar Cursos e Cadeiras dos teus JSONs...');

        $jsonCursos = File::get(database_path('data/cursos_ua.json'));
        $cursos = json_decode($jsonCursos, true);

        foreach ($cursos as $cursoData) {
            Course::firstOrCreate(
                ['name' => $cursoData['nome_curso']],
                ['url' => $cursoData['url_curso']]
            );
        }

        $this->command->info('✅ Cursos importados!');

        $cursoMtc = Course::where('name', 'Multimédia e Tecnologias da Comunicação')->first();

        if (!$cursoMtc) {
            $this->command->warn('⚠️ Curso MTC não encontrado no JSON. Seed das cadeiras MTC ignorado.');
            return;
        }

        $jsonCadeiras = File::get(database_path('data/cadeiras_mtc.json'));
        $cadeiras = json_decode($jsonCadeiras, true);
        $demoSubjects = collect();

        foreach ($cadeiras as $cadeiraData) {
            $subject = Subject::firstOrCreate(
                ['name' => $cadeiraData['nome_uc']],
                ['url' => $cadeiraData['url_uc']]
            );

            $this->ensureSubjectUcMetadata($subject);
            $cursoMtc->subjects()->syncWithoutDetaching([$subject->id]);
            $demoSubjects->push($subject->fresh());
        }

        $this->command->info('✅ Cadeiras de MTC ligadas e importadas com sucesso!');

        $student = User::updateOrCreate(
            ['email' => 'aluno@ua.pt'],
            [
                'name' => 'Aluno de Teste MTC',
                'password' => Hash::make('password123'),
                'course_id' => $cursoMtc->id,
                'role' => 'aluno',
                'email_verified_at' => now(),
            ]
        );

        $teacher = User::updateOrCreate(
            ['email' => 'professor@ua.pt'],
            [
                'name' => 'Professor de Teste',
                'password' => Hash::make('password123'),
                'course_id' => $cursoMtc->id,
                'role' => 'professor',
                'email_verified_at' => now(),
            ]
        );

        $membershipCount = 0;

        foreach ($demoSubjects as $subject) {
            if (!$subject) {
                continue;
            }

            if (!$subject->created_by) {
                $subject->forceFill([
                    'created_by' => $teacher->id,
                ])->save();
            }

            $this->upsertSubjectMembership($subject, $student, 'student');
            $this->upsertSubjectMembership($subject, $teacher, 'teacher');
            $membershipCount += 2;
        }

        Log::info('[TUTS][SubjectMembership] seeded memberships', [
            'subjects' => $demoSubjects->count(),
            'memberships' => $membershipCount,
        ]);

        $this->command->info('👨‍🎓 Aluno de teste: aluno@ua.pt / password123');
        $this->command->info('👨‍🏫 Professor de teste: professor@ua.pt / password123');

        $this->call(AdminBackofficeDemoSeeder::class);
        $this->call(TutsDemoAccountsSeeder::class);
    }

    private function ensureSubjectUcMetadata(Subject $subject): void
    {
        $updates = [];

        if (!$subject->acronym) {
            $updates['acronym'] = $this->acronymFor($subject->name);
        }

        if (!$subject->enrollment_code) {
            $updates['enrollment_code'] = $this->uniqueEnrollmentCode($subject);
        }

        if (!$subject->status) {
            $updates['status'] = 'active';
        }

        if (!$subject->source) {
            $updates['source'] = 'seed';
        }

        if ($updates !== []) {
            $subject->forceFill($updates)->save();
        }
    }

    private function upsertSubjectMembership(Subject $subject, User $user, string $role): void
    {
        DB::table('subject_user')->updateOrInsert(
            [
                'subject_id' => $subject->id,
                'user_id' => $user->id,
                'role' => $role,
            ],
            [
                'status' => 'active',
                'source' => 'seed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function acronymFor(string $name): string
    {
        $ascii = Str::ascii($name);
        $words = preg_split('/\s+/', trim($ascii)) ?: [];

        $letters = collect($words)
            ->map(fn(string $word) => preg_replace('/[^A-Za-z0-9]/', '', $word) ?: '')
            ->filter(fn(string $word) => strlen($word) > 2)
            ->take(4)
            ->map(fn(string $word) => strtoupper(substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $ascii) ?: 'UC', 0, 4));
    }

    private function uniqueEnrollmentCode(Subject $subject): string
    {
        $base = $this->acronymFor($subject->name);
        $base = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($base)) ?: 'UC', 0, 5);
        $suffix = str_pad((string) $subject->id, 4, '0', STR_PAD_LEFT);
        $candidate = $base . $suffix;
        $attempt = 1;

        while (
            Subject::query()
                ->where('enrollment_code', $candidate)
                ->where('id', '!=', $subject->id)
                ->exists()
        ) {
            $candidate = $base . $suffix . $attempt;
            $attempt++;
        }

        return $candidate;
    }
}
