<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

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

        foreach ($cadeiras as $cadeiraData) {
            $subject = Subject::firstOrCreate(
                ['name' => $cadeiraData['nome_uc']],
                ['url' => $cadeiraData['url_uc']]
            );

            $cursoMtc->subjects()->syncWithoutDetaching([$subject->id]);
        }

        $this->command->info('✅ Cadeiras de MTC ligadas e importadas com sucesso!');

        User::updateOrCreate(
            ['email' => 'aluno@ua.pt'],
            [
                'name' => 'Aluno de Teste MTC',
                'password' => Hash::make('password123'),
                'course_id' => $cursoMtc->id,
                'role' => 'aluno',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'professor@ua.pt'],
            [
                'name' => 'Professor de Teste',
                'password' => Hash::make('password123'),
                'course_id' => $cursoMtc->id,
                'role' => 'professor',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('👨‍🎓 Aluno de teste: aluno@ua.pt / password123');
        $this->command->info('👨‍🏫 Professor de teste: professor@ua.pt / password123');
    }
}
