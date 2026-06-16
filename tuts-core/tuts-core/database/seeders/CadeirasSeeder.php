<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Subject; // ✅ Mudámos aqui para Subject!

class CadeirasSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ler o ficheiro JSON
        $json = File::get(database_path('data/cadeiras_mtc.json'));
        $cadeiras = json_decode($json, true);

        // 2. Injetar as cadeiras no PostgreSQL
        foreach ($cadeiras as $cadeira) {
            Subject::create([ // ✅ E mudámos aqui também para Subject!
                'name' => $cadeira['nome_uc'],
                'url'  => $cadeira['url_uc'],

                // ATENÇÃO: Se a tua tabela 'subjects' obrigar a ter um 'course_id', 
                // tira os comentários da linha abaixo e mete o ID correspondente.
                // 'course_id' => 1 
            ]);
        }

        $this->command->info('✅ Cadeiras de MTC injetadas com sucesso no PostgreSQL!');
    }
}
