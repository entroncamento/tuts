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

        // 1. Ler e importar os Cursos
        $jsonCursos = File::get(database_path('data/cursos_ua.json'));
        $cursos = json_decode($jsonCursos, true);

        foreach ($cursos as $cursoData) {
            Course::firstOrCreate(
                ['name' => $cursoData['nome_curso']],
                ['url' => $cursoData['url_curso']]
            );
        }
        $this->command->info('✅ Cursos importados!');

        // 2. Encontrar o ID do curso de MTC
        $cursoMtc = Course::where('name', 'Multimédia e Tecnologias da Comunicação')->first();

        // 3. Ler e importar as Cadeiras de MTC
        if ($cursoMtc) {
            $jsonCadeiras = File::get(database_path('data/cadeiras_mtc.json'));
            $cadeiras = json_decode($jsonCadeiras, true);

            foreach ($cadeiras as $cadeiraData) {
                // Procura se a cadeira já existe. Se não existir, cria-a na tabela subjects.
                $subject = Subject::firstOrCreate(
                    ['name' => $cadeiraData['nome_uc']],
                    ['url' => $cadeiraData['url_uc']]
                );

                // Liga a cadeira ao curso MTC na tabela pivot 'course_subject'
                $cursoMtc->subjects()->syncWithoutDetaching([$subject->id]);
            }
            $this->command->info('✅ Cadeiras de MTC ligadas e importadas com sucesso!');

            // 4. A MAGIA FINAL: Criar o Aluno de Teste (ID 1) e matriculá-lo em MTC!
            User::firstOrCreate(
                ['email' => 'aluno@ua.pt'],
                [
                    'name' => 'Aluno de Teste MTC',
                    'password' => Hash::make('password123'),
                    'course_id' => $cursoMtc->id // <-- Associa o aluno ao curso!
                ]
            );
            $this->command->info('👨‍🎓 Aluno de teste matriculado em MTC!');
        }
    }
}
