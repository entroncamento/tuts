<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Course; // <-- Confirma se o teu model se chama Course ou Curso

class CursosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ler o ficheiro JSON
        $json = File::get(database_path('data/cursos_ua.json'));
        $cursos = json_decode($json, true);

        // 2. Injetar cada curso na Base de Dados PostgreSQL
        foreach ($cursos as $curso) {
            Course::create([
                'name' => $curso['nome_curso'], // <-- AQUI: Substitui 'name' pela coluna da tua tabela
                'url'  => $curso['url_curso']   // <-- AQUI: Substitui 'url' pela coluna da tua tabela
            ]);
        }

        $this->command->info('✅ Cursos da UA injetados com sucesso no PostgreSQL!');
    }
}
