<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\StudentMessageAnalysis;
use App\Models\SubjectSection;
use App\Models\SubjectMaterial;
use App\Models\PersonalMaterial;
use App\Models\AuditLog;
use App\Models\StudySpace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TutsDemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚜 Starting TutsDemoAccountsSeeder...');

        // 1. Course initialization (MTC)
        $course = Course::firstOrCreate(
            ['name' => 'Multimédia e Tecnologias da Comunicação'],
            ['url' => 'https://www.ua.pt/pt/curso/464']
        );
        $this->command->info('✅ Course: Multimédia e Tecnologias da Comunicação');

        // 2. Demo accounts data mapping
        $superadminData = [
            'email' => 'superadmin@ua.pt',
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ];

        $adminData = [
            'email' => 'admin@ua.pt',
            'name' => 'Admin Demo',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ];

        $teachersData = [
            [
                'email' => 'professor.demo1@ua.pt',
                'name' => 'Professor Demo 1',
                'password' => Hash::make('password'),
                'role' => 'professor',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'professor.demo2@ua.pt',
                'name' => 'Professor Demo 2',
                'password' => Hash::make('password'),
                'role' => 'professor',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'professor.demo3@ua.pt',
                'name' => 'Professor Demo 3',
                'password' => Hash::make('password'),
                'role' => 'professor',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
        ];

        $studentsData = [
            [
                'email' => 'aluno.demo1@ua.pt',
                'name' => 'Aluno Demo 1',
                'password' => Hash::make('password'),
                'role' => 'aluno',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'aluno.demo2@ua.pt',
                'name' => 'Aluno Demo 2',
                'password' => Hash::make('password'),
                'role' => 'aluno',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'aluno.demo3@ua.pt',
                'name' => 'Aluno Demo 3',
                'password' => Hash::make('password'),
                'role' => 'aluno',
                'course_id' => $course->id,
                'email_verified_at' => now(),
            ],
        ];

        // 3. User creation / updates (Idempotent)
        $superadmin = User::updateOrCreate(['email' => $superadminData['email']], $superadminData);
        $admin = User::updateOrCreate(['email' => $adminData['email']], $adminData);

        $teachers = [];
        foreach ($teachersData as $tData) {
            $teachers[] = User::updateOrCreate(['email' => $tData['email']], $tData);
        }

        $students = [];
        foreach ($studentsData as $sData) {
            $students[] = User::updateOrCreate(['email' => $sData['email']], $sData);
        }

        $this->command->info('✅ Users seeded: Super Admin, Admin, 3 Teachers, and 3 Students.');

        // 4. Subjects / UCs seeding
        $subjectsData = [
            [
                'name' => 'Sistemas de Comunicação Multimédia',
                'acronym' => 'SCM',
                'enrollment_code' => 'SCM2026',
                'url' => 'https://www.ua.pt/pt/uc/12246',
                'color' => 'purple',
                'created_by' => $teachers[0]->id,
                'status' => 'active',
                'source' => 'seed',
                'academic_year' => '2025/2026',
                'year' => 3,
                'semester' => 1,
            ],
            [
                'name' => 'Bases de Dados e Tecnologias Server-Side',
                'acronym' => 'BDTSS',
                'enrollment_code' => 'BDTSS2026',
                'url' => 'https://www.ua.pt/pt/uc/12247',
                'color' => 'blue',
                'created_by' => $teachers[1]->id,
                'status' => 'active',
                'source' => 'seed',
                'academic_year' => '2025/2026',
                'year' => 2,
                'semester' => 2,
            ],
            [
                'name' => 'Projeto Multimédia',
                'acronym' => 'PM',
                'enrollment_code' => 'PM2026',
                'url' => 'https://www.ua.pt/pt/uc/12248',
                'color' => 'green',
                'created_by' => $teachers[2]->id,
                'status' => 'active',
                'source' => 'seed',
                'academic_year' => '2025/2026',
                'year' => 3,
                'semester' => 2,
            ],
        ];

        $subjects = [];
        foreach ($subjectsData as $subData) {
            $subjects[] = Subject::updateOrCreate(['name' => $subData['name']], $subData);
        }

        // Link subjects to MTC course
        $subjectIds = array_map(fn($s) => $s->id, $subjects);
        $course->subjects()->syncWithoutDetaching($subjectIds);

        $this->command->info('✅ Subjects seeded and linked to course.');

        // 5. Connect Teachers <-> Subjects (Pivot table)
        foreach ($subjects as $index => $subject) {
            $teacher = $teachers[$index];
            $this->upsertSubjectMembership($subject->id, $teacher->id, 'teacher');
        }
        $this->command->info('✅ Teachers linked to their respective Subjects.');

        // 6. Connect Students <-> Subjects (Pivot table)
        // Aluno Demo 1 (index 0) enrolled in SCM (0), BDTSS (1), PM (2)
        $this->upsertSubjectMembership($subjects[0]->id, $students[0]->id, 'student');
        $this->upsertSubjectMembership($subjects[1]->id, $students[0]->id, 'student');
        $this->upsertSubjectMembership($subjects[2]->id, $students[0]->id, 'student');

        // Aluno Demo 2 (index 1) enrolled in SCM (0), BDTSS (1)
        $this->upsertSubjectMembership($subjects[0]->id, $students[1]->id, 'student');
        $this->upsertSubjectMembership($subjects[1]->id, $students[1]->id, 'student');

        // Aluno Demo 3 (index 2) enrolled in PM (2)
        $this->upsertSubjectMembership($subjects[2]->id, $students[2]->id, 'student');
        $this->command->info('✅ Students linked to Subjects according to the spec.');

        // 7. Mock sections and materials for each subject
        $materialsMock = [
            'SCM' => [
                'sections' => [
                    'Apresentação e Introdução' => [
                        ['name' => 'Acetatos de Apresentação da UC', 'type' => 'pdf', 'size' => 1250000],
                        ['name' => 'Introdução à Codificação de Vídeo H.264', 'type' => 'pdf', 'size' => 3450000],
                    ],
                    'Laboratórios Práticos' => [
                        ['name' => 'Guião Prático 1 - Comandos FFmpeg Avançados', 'type' => 'pdf', 'size' => 1980000],
                    ],
                ]
            ],
            'BDTSS' => [
                'sections' => [
                    'Modelagem de Dados e SQL' => [
                        ['name' => 'Manual de Desenho e Normalização BD', 'type' => 'pdf', 'size' => 4500000],
                        ['name' => 'Ficha Teórico-Prática: Joins e Indexes', 'type' => 'pdf', 'size' => 880000],
                    ],
                    'Node.js & Express' => [
                        ['name' => 'Guião Prático 2 - REST API com Express', 'type' => 'pdf', 'size' => 1700000],
                    ],
                ]
            ],
            'PM' => [
                'sections' => [
                    'Planeamento e Design' => [
                        ['name' => 'Template de Proposta de Projeto', 'type' => 'docx', 'size' => 320000],
                        ['name' => 'Documento de Especificações de UX/UI', 'type' => 'pdf', 'size' => 2400000],
                    ],
                    'Metodologias Ágeis' => [
                        ['name' => 'Guia de Boas Práticas Scrum no PM', 'type' => 'pdf', 'size' => 1200000],
                    ],
                ]
            ]
        ];

        foreach ($subjects as $subject) {
            $acronym = $subject->acronym;
            if (!isset($materialsMock[$acronym])) continue;

            $sectMock = $materialsMock[$acronym]['sections'];
            $order = 1;
            foreach ($sectMock as $secName => $mats) {
                // Create section
                $section = SubjectSection::updateOrCreate(
                    ['subject_id' => $subject->id, 'name' => $secName],
                    [
                        'description' => "Secção sobre {$secName}",
                        'visible_to_students' => true,
                        'visible_from' => now()->subDays(15),
                        'order' => $order++,
                    ]
                );

                // Create materials
                foreach ($mats as $matInfo) {
                    SubjectMaterial::updateOrCreate(
                        ['subject_id' => $subject->id, 'section_id' => $section->id, 'name' => $matInfo['name']],
                        [
                            'type' => $matInfo['type'],
                            'mime_type' => $matInfo['type'] === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'size_bytes' => $matInfo['size'],
                            'path' => "materials/" . Str::slug($subject->acronym) . "/" . Str::slug($matInfo['name']) . "." . $matInfo['type'],
                            'url' => "http://localhost:8000/storage/mock/" . Str::slug($matInfo['name']) . "." . $matInfo['type'],
                            'source' => 'official',
                            'verified_by_teacher' => true,
                        ]
                    );
                }
            }
        }
        $this->command->info('✅ Subject sections and materials seeded successfully.');

        // 8. Seeding Chats, Messages, and StudentMessageAnalyses (Dashboard Stats)
        // Aluno 1 chats
        $this->seedDemoChat(
            $students[0],
            $subjects[0],
            $course,
            'HLS vs DASH Streaming',
            'HLS e DASH',
            'Protocolos de Vídeo',
            [
                ['user' => 'Qual é a principal diferença entre HLS e MPEG-DASH?', 'confusion' => 0.5, 'frustration' => 0.1, 'urgency' => 0.4, 'priority' => 'low', 'sentiment' => 'neutral'],
                ['ai' => 'HLS foi criado pela Apple e usa formatos MPEG-TS ou fragmentos MP4. O MPEG-DASH é um padrão ISO aberto. Na prática, o iOS exige HLS para streaming nativo, enquanto o DASH é mais flexível no ecossistema Android e browsers.'],
                ['user' => 'Mas o DASH é mais eficiente para taxas de bits dinâmicas?', 'confusion' => 0.7, 'frustration' => 0.3, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                ['ai' => 'Ambos suportam Adaptive Bitrate (ABR). O DASH é ligeiramente mais eficiente por ter menos overhead nos manifestos, mas com o HLS v2 (fMP4) a diferença de performance é mínima.']
            ]
        );

        $this->seedDemoChat(
            $students[0],
            $subjects[1],
            $course,
            'Índices B-Tree em PostgreSQL',
            'Índices e Otimização',
            'PostgreSQL',
            [
                ['user' => 'Quando devo criar um índice B-Tree numa tabela?', 'confusion' => 0.6, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                ['ai' => 'Deves criar um índice B-Tree em colunas que usas frequentemente em cláusulas WHERE (com operadores de igualdade ou intervalos como >, <, BETWEEN) e em JOINS. Evita em tabelas muito pequenas ou colunas com muitas atualizações.'],
                ['user' => 'E em chaves estrangeiras, vale a pena?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'positive'],
                ['ai' => 'Sim! Quase sempre vale a pena indexar chaves estrangeiras, pois ajuda imenso na performance de JOINS e nas restrições de ON DELETE CASCADE.']
            ]
        );

        // Aluno 2 chats
        $this->seedDemoChat(
            $students[1],
            $subjects[1],
            $course,
            'Middlewares no Express',
            'Middleware',
            'Express.js',
            [
                ['user' => 'Por que é que o Express fica pendurado se eu não chamar o next() num middleware?', 'confusion' => 0.9, 'frustration' => 0.8, 'urgency' => 0.8, 'priority' => 'high', 'sentiment' => 'negative'],
                ['ai' => 'O Express funciona como uma cadeia de middlewares. Se um middleware não envia uma resposta (res.send, res.json) e não chama next(), o ciclo de pedido-resposta é interrompido e o browser fica à espera infinitamente.']
            ]
        );

        // Aluno 3 chats
        $this->seedDemoChat(
            $students[2],
            $subjects[2],
            $course,
            'Organização da Sprint',
            'Scrum',
            'Gestão de Projetos',
            [
                ['user' => 'Como dividimos as tarefas no Sprint Planning para o Projeto Multimédia?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                ['ai' => 'No Sprint Planning, a equipa analisa o Product Backlog, estima o esforço (ex: Story Points) e move itens para o Sprint Backlog. Devem atribuir tarefas específicas a cada membro e garantir que o total de pontos não excede a vossa velocidade estimada.']
            ]
        );

        $this->command->info('✅ Chats and student message analyses created for students.');

        // 9. Personal Materials
        PersonalMaterial::firstOrCreate(
            ['owner_id' => $students[0]->id, 'original_name' => 'Resumos_SCM_Estudo.pdf'],
            [
                'uploaded_by' => $students[0]->id,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 1250300,
                'storage_disk' => 'local',
                'storage_key' => 'personal/resumos_scm_estudo.pdf'
            ]
        );

        PersonalMaterial::firstOrCreate(
            ['owner_id' => $students[1]->id, 'original_name' => 'Apontamentos_BDTSS_Normalizacao.docx'],
            [
                'uploaded_by' => $students[1]->id,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx',
                'size_bytes' => 450000,
                'storage_disk' => 'local',
                'storage_key' => 'personal/apontamentos_bdtss_normalizacao.docx'
            ]
        );
        $this->command->info('✅ Personal materials seeded for students.');

        // 10. Audit Logs seeding
        AuditLog::firstOrCreate(
            ['actor_user_id' => $admin->id, 'action' => 'user_roles_viewed', 'created_at' => now()->subHours(2)],
            [
                'target_type' => 'User',
                'target_id' => null,
                'metadata' => ['viewed_by' => 'admin', 'tab' => 'roles'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ]
        );

        AuditLog::firstOrCreate(
            ['actor_user_id' => $teachers[0]->id, 'action' => 'material_uploaded', 'created_at' => now()->subDays(1)],
            [
                'target_type' => 'SubjectMaterial',
                'target_id' => '1',
                'metadata' => ['subject_acronym' => 'SCM', 'fileName' => 'Acetatos de Apresentação da UC'],
                'ip_address' => '193.136.2.14',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            ]
        );

        AuditLog::firstOrCreate(
            ['actor_user_id' => $superadmin->id, 'action' => 'settings_updated', 'created_at' => now()->subMinutes(30)],
            [
                'target_type' => 'SystemConfig',
                'target_id' => null,
                'metadata' => ['changed' => ['rag_active' => true, 'maintenance_mode' => false]],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
            ]
        );
        $this->command->info('✅ Audit logs seeded for Admin/Teachers/Super Admin.');

        // 11. Study Spaces seeding (if available)
        StudySpace::firstOrCreate(
            ['user_id' => $students[0]->id, 'name' => 'Sala de Trabalho Multimédia'],
            [
                'description' => 'Espaço privado do Aluno 1 para juntar documentação e planear o projeto.',
                'cover' => 'covers/cover1.jpg',
                'color' => 'blue',
            ]
        );

        StudySpace::firstOrCreate(
            ['user_id' => $students[1]->id, 'name' => 'Desenvolvimento Web'],
            [
                'description' => 'Espaço para reunir resumos e referências de bases de dados.',
                'cover' => 'covers/cover2.jpg',
                'color' => 'green',
            ]
        );
        $this->command->info('✅ Study spaces seeded.');

        $this->command->info('🚜 TutsDemoAccountsSeeder completed successfully!');
    }

    private function upsertSubjectMembership(int $subjectId, int $userId, string $role): void
    {
        DB::table('subject_user')->updateOrInsert(
            [
                'subject_id' => $subjectId,
                'user_id' => $userId,
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

    private function seedDemoChat(User $student, Subject $subject, Course $course, string $title, string $topic, string $subtopic, array $msgs): void
    {
        $studentHash = md5((string)$student->id);

        $chat = Chat::firstOrCreate(
            ['user_id' => $student->id, 'subject_id' => $subject->id, 'title' => $title],
            [
                'context_type' => 'subject',
                'is_temporary' => false,
                'created_at' => now()->subDays(5),
                'updated_at' => now(),
            ]
        );

        $lastMsgDate = now()->subDays(5);

        foreach ($msgs as $index => $msg) {
            $role = isset($msg['user']) ? 'user' : 'ai';
            $content = isset($msg['user']) ? $msg['user'] : $msg['ai'];
            $msgDate = $lastMsgDate->copy()->addMinutes(rand(1, 5));

            $message = Message::firstOrCreate(
                ['chat_id' => $chat->id, 'content' => $content, 'role' => $role],
                [
                    'created_at' => $msgDate,
                    'updated_at' => $msgDate,
                ]
            );

            $lastMsgDate = $msgDate;

            if ($role === 'user') {
                StudentMessageAnalysis::updateOrCreate(
                    ['message_id' => $message->id],
                    [
                        'chat_id' => $chat->id,
                        'subject_id' => $subject->id,
                        'course_id' => $course->id,
                        'student_hash' => $studentHash,
                        'role' => 'user',
                        'language' => 'pt',
                        'question_excerpt' => Str::limit($content, 280),
                        'topic' => $topic,
                        'subtopic' => $subtopic,
                        'intent' => 'duvida',
                        'confusion_score' => $msg['confusion'],
                        'frustration_score' => $msg['frustration'],
                        'urgency_score' => $msg['urgency'],
                        'difficulty_level' => $msg['confusion'] >= 0.7 ? 'hard' : ($msg['confusion'] >= 0.4 ? 'medium' : 'easy'),
                        'priority' => $msg['priority'],
                        'sentiment' => $msg['sentiment'],
                        'is_recurring' => false,
                        'needs_teacher_attention' => $msg['frustration'] >= 0.6 || $msg['priority'] === 'critical',
                        'llm_summary' => "Dúvida do aluno sobre " . $topic . " (" . $subtopic . ")",
                        'suggested_teacher_action' => "Rever conceitos de " . $topic . " e apoiar o aluno.",
                        'analysis_provider' => 'rag',
                        'analysis_version' => '1.0',
                        'processed_at' => $msgDate,
                        'created_at' => $msgDate,
                        'updated_at' => $msgDate,
                    ]
                );
            }
        }

        $chat->update(['updated_at' => $lastMsgDate]);
    }
}
