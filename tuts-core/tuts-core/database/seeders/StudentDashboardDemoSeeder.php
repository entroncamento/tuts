<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\StudentMessageAnalysis;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StudentDashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obter ou criar o Curso MTC
        $course = Course::firstOrCreate(
            ['name' => 'Multimédia e Tecnologias da Comunicação'],
            ['url' => 'https://www.ua.pt/pt/curso/464']
        );

        // 2. Obter ou criar a UC "Redes de Computadores"
        $subject = Subject::where('name', 'Redes de Computadores')
            ->orWhere('acronym', 'RC')
            ->first();

        if (!$subject) {
            $subject = Subject::create([
                'name' => 'Redes de Computadores',
                'url' => 'https://www.ua.pt/pt/uc/12245',
                'acronym' => 'RC',
                'enrollment_code' => 'RC2025',
                'status' => 'active',
                'source' => 'seed',
                'academic_year' => '2025/2026',
                'year' => 2,
                'semester' => 1,
            ]);
        }

        // Associar a UC ao curso
        $course->subjects()->syncWithoutDetaching([$subject->id]);

        // 3. Criar ou atualizar o Professor de Teste
        $professor = User::updateOrCreate(
            ['email' => 'professor.dashboard@tuts.test'],
            [
                'name' => 'Professor Redes Dashboard',
                'password' => Hash::make('password'),
                'course_id' => $course->id,
                'role' => 'professor',
                'email_verified_at' => now(),
            ]
        );

        // Associar professor à UC
        DB::table('subject_user')->updateOrInsert(
            [
                'subject_id' => $subject->id,
                'user_id' => $professor->id,
                'role' => 'teacher',
            ],
            [
                'status' => 'active',
                'source' => 'seed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Se a UC não tiver criador associado
        if (!$subject->created_by) {
            $subject->update(['created_by' => $professor->id]);
        }

        // 4. Criar ou atualizar o Aluno de Teste
        $student = User::updateOrCreate(
            ['email' => 'aluno.dashboard@tuts.test'],
            [
                'name' => 'Aluno Dashboard Teste',
                'password' => Hash::make('password'),
                'course_id' => $course->id,
                'role' => 'aluno',
                'email_verified_at' => now(),
            ]
        );

        // Associar aluno à UC
        DB::table('subject_user')->updateOrInsert(
            [
                'subject_id' => $subject->id,
                'user_id' => $student->id,
                'role' => 'student',
            ],
            [
                'status' => 'active',
                'source' => 'seed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // 5. Idempotência: Limpar apenas chats e análises demo anteriores
        $this->command->info('🧹 Limpando dados de demonstração antigos...');

        $studentHash = md5((string)$student->id);

        // Apagar StudentMessageAnalysis associados a este aluno e a esta UC
        StudentMessageAnalysis::where('subject_id', $subject->id)
            ->where('student_hash', $studentHash)
            ->delete();

        // Obter IDs dos chats demo existentes para apagar mensagens (caso as constraints de DB necessitem, embora cascade esteja ativo)
        $oldDemoChatIds = Chat::where('user_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('title', 'like', '[DEMO]%')
            ->pluck('id')
            ->toArray();

        if (!empty($oldDemoChatIds)) {
            Message::whereIn('chat_id', $oldDemoChatIds)->delete();
            Chat::whereIn('id', $oldDemoChatIds)->delete();
        }

        $this->command->info('🚜 Injetando dados fake de conversas de Redes de Computadores...');

        // 6. Dados estruturados dos 20 Chats para gerar conversas realistas
        $chatsData = [
            [
                'title' => '[DEMO] Dúvidas sobre modelo OSI',
                'topic' => 'Modelo OSI',
                'subtopic' => 'Camadas e Funções',
                'messages' => [
                    ['user' => 'O que é o modelo OSI e quais as suas 7 camadas?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O modelo OSI (Open Systems Interconnection) é um modelo conceitual que padroniza as funções de comunicação de rede. Suas 7 camadas são: Física, Enlace de Dados, Rede, Transporte, Sessão, Apresentação e Aplicação.'],
                    ['user' => 'Qual a diferença entre a camada de transporte e de rede?', 'confusion' => 0.65, 'frustration' => 0.2, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'A camada de rede (L3) é responsável pelo endereçamento lógico e roteamento dos pacotes (como IP), enquanto a camada de transporte (L4) garante a entrega fiável e o controlo de fluxo ponta a ponta (como TCP/UDP).'],
                    ['user' => 'E qual camada trata de erros físicos de transmissão?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'positive'],
                    ['ai' => 'É a camada de Enlace de Dados (Layer 2), que lida com o controlo de erros e fluxo no próprio meio físico através de tramas.']
                ]
            ],
            [
                'title' => '[DEMO] Funcionamento do TCP/IP',
                'topic' => 'TCP/IP',
                'subtopic' => 'Arquitetura de Protocolos',
                'messages' => [
                    ['user' => 'Como funciona a arquitetura TCP/IP em comparação com o modelo OSI?', 'confusion' => 0.5, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'A arquitetura TCP/IP é mais simplificada que o modelo OSI, contendo apenas 4 camadas: Acesso à Rede, Internet, Transporte e Aplicação.'],
                    ['user' => 'Quais os principais protocolos da camada de Internet?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O principal protocolo é o IP (IPv4 e IPv6), além de protocolos auxiliares como ICMP e ARP.'],
                    ['user' => 'E por que é que o TCP fica na camada de transporte?', 'confusion' => 0.8, 'frustration' => 0.7, 'urgency' => 0.8, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'O TCP fica na camada de transporte porque ele garante a transmissão confiável de dados de host para host, providenciando controle de fluxo, sequenciação e retransmissões.']
                ]
            ],
            [
                'title' => '[DEMO] Exercícios de subnetting',
                'topic' => 'Subnetting',
                'subtopic' => 'Máscaras de Rede e VLSM',
                'messages' => [
                    ['user' => 'Como é que calculo sub-redes a partir de um IP /26?', 'confusion' => 0.85, 'frustration' => 0.8, 'urgency' => 0.9, 'priority' => 'critical', 'sentiment' => 'negative'],
                    ['ai' => 'Uma máscara /26 significa que temos 26 bits para rede e 6 bits para hosts. Isso dá 2^6 = 64 endereços por sub-rede (sendo 62 utilizáveis, pois o primeiro é ID de rede e o último é broadcast).'],
                    ['user' => 'Se eu tiver a rede 192.168.1.0/26, quais são as sub-redes?', 'confusion' => 0.7, 'frustration' => 0.5, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'neutral'],
                    ['ai' => 'As sub-redes serão: 192.168.1.0/26 (IPs utilizáveis de .1 a .62), 192.168.1.64/26 (IPs utilizáveis de .65 a .126), 192.168.1.128/26 e 192.168.1.192/26.'],
                    ['user' => 'Estou baralhado no cálculo do broadcast do terceiro bloco.', 'confusion' => 0.9, 'frustration' => 0.9, 'urgency' => 0.9, 'priority' => 'critical', 'sentiment' => 'negative'],
                    ['ai' => 'Para o terceiro bloco (192.168.1.128/26), os endereços vão de .128 a .191. O IP de rede é .128, os utilizáveis vão de .129 a .190, e o broadcast é .191 (que é imediatamente anterior ao início do quarto bloco .192).']
                ]
            ],
            [
                'title' => '[DEMO] Diferença entre TCP e UDP',
                'topic' => 'TCP vs UDP',
                'subtopic' => 'Protocolos de Transporte',
                'messages' => [
                    ['user' => 'Qual é a principal diferença entre o protocolo TCP e o UDP?', 'confusion' => 0.2, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O TCP é orientado à ligação e fiável, garantindo que os dados chegam na ordem correta. O UDP não é orientado à ligação e não garante a entrega, sendo focado em velocidade e baixa latência.'],
                    ['user' => 'Quando devo usar UDP em vez de TCP?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Deves usar UDP em aplicações em tempo real como streaming de vídeo, chamadas de voz VoIP e jogos online, onde pequenas perdas de pacotes são toleráveis.'],
                    ['user' => 'O cabeçalho do UDP é muito menor do que o do TCP?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim! O cabeçalho UDP tem apenas 8 bytes, enquanto o do TCP tem no mínimo 20 bytes, reduzindo consideravelmente a sobrecarga (overhead).']
                ]
            ],
            [
                'title' => '[DEMO] Camada de aplicação',
                'topic' => 'Camada de aplicação',
                'subtopic' => 'Protocolos L7',
                'messages' => [
                    ['user' => 'Quais são os principais protocolos da camada de aplicação do modelo OSI?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Os principais protocolos incluem HTTP, HTTPS, DNS, FTP, SMTP, DHCP e SSH.'],
                    ['user' => 'O DNS pertence a esta camada?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim, o DNS (Domain Name System) atua na camada de aplicação para traduzir nomes de domínio legíveis por humanos em endereços IP.'],
                    ['user' => 'E o protocolo SSH?', 'confusion' => 0.2, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim, o SSH também está na camada de aplicação e é usado para acesso e administração remota segura.']
                ]
            ],
            [
                'title' => '[DEMO] DNS e resolução de nomes',
                'topic' => 'DNS',
                'subtopic' => 'Resolução de Nomes',
                'messages' => [
                    ['user' => 'O que faz o DNS na internet?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O DNS funciona como a lista telefónica da internet, mapeando nomes como google.com para endereços IP como 142.250.180.14.'],
                    ['user' => 'Como funciona uma consulta DNS recursiva?', 'confusion' => 0.7, 'frustration' => 0.4, 'urgency' => 0.6, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'Numa consulta recursiva, o teu computador pergunta ao resolver local, que depois interroga os servidores raiz, os TLD (.com, .pt) e, finalmente, o servidor autoritativo daquele domínio.'],
                    ['user' => 'O que acontece se o servidor autoritativo falhar?', 'confusion' => 0.8, 'frustration' => 0.6, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'Se o servidor autoritativo falhar e a resposta não estiver em cache, o resolver local retornará um erro (como NXDOMAIN ou Server Failure) e o site ficará inacessível.']
                ]
            ],
            [
                'title' => '[DEMO] HTTP vs HTTPS',
                'topic' => 'HTTP/HTTPS',
                'subtopic' => 'Segurança na Web',
                'messages' => [
                    ['user' => 'Qual é a diferença real entre HTTP e HTTPS?', 'confusion' => 0.2, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'A principal diferença é a segurança. O HTTPS utiliza SSL/TLS para cifrar as comunicações entre o browser e o servidor, garantindo confidencialidade e integridade.'],
                    ['user' => 'Qual é a porta por defeito de cada um?', 'confusion' => 0.1, 'frustration' => 0.1, 'urgency' => 0.1, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O HTTP utiliza a porta 80, enquanto o HTTPS utiliza a porta 443.'],
                    ['user' => 'Como funciona a cifragem no TLS?', 'confusion' => 0.75, 'frustration' => 0.3, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'Usa cifragem assimétrica (chaves públicas/privadas) durante o handshake para estabelecer uma chave simétrica partilhada de sessão, que cifra a transferência real de dados.']
                ]
            ],
            [
                'title' => '[DEMO] Routing e encaminhamento',
                'topic' => 'routing',
                'subtopic' => 'Protocolos de Roteamento',
                'messages' => [
                    ['user' => 'Como é que funciona o routing e o encaminhamento de pacotes?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'O roteamento (routing) é o processo de determinar a melhor rota para enviar pacotes de uma rede para outra através de routers com tabelas de routing.'],
                    ['user' => 'Qual a diferença entre routing estático e dinâmico?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O routing estático é configurado manualmente pelo administrador. O routing dinâmico usa protocolos (como OSPF ou BGP) para descobrir rotas automaticamente e reagir a falhas.'],
                    ['user' => 'O RIP ainda é utilizado?', 'confusion' => 0.6, 'frustration' => 0.3, 'urgency' => 0.4, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Muito raramente, apenas em laboratórios ou redes muito pequenas, pois foi largamente substituído pelo OSPF devido à sua limitação de máximo 15 saltos.']
                ]
            ],
            [
                'title' => '[DEMO] Endereçamento IPv4',
                'topic' => 'endereçamento IPv4',
                'subtopic' => 'Classes de IP',
                'messages' => [
                    ['user' => 'Quais as classes de endereços no IPv4?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Os endereços IPv4 dividem-se em Classe A (1 a 126), Classe B (128 a 191), Classe C (192 a 223), Classe D (Multicast) e Classe E (Experimental).'],
                    ['user' => 'O que são endereços IPv4 privados?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Endereços privados não são roteáveis na internet pública. Exemplos: 10.0.0.0/8, 172.16.0.0/12 e 192.168.0.0/16.'],
                    ['user' => 'Por que existem IPs privados?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Para economizar endereços IPv4 públicos e aumentar a segurança interna das redes empresariais e domésticas.']
                ]
            ],
            [
                'title' => '[DEMO] Máscaras de rede',
                'topic' => 'Subnetting',
                'subtopic' => 'Cálculo de Máscaras',
                'messages' => [
                    ['user' => 'Para que serve a máscara de rede?', 'confusion' => 0.4, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'A máscara de rede serve para separar a parte do endereço IP que identifica a rede da parte que identifica o host.'],
                    ['user' => 'Se eu tiver o IP 192.168.1.10 com máscara 255.255.255.0, o que significa?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Significa que a rede é 192.168.1.0 e o host é o 10. A máscara identifica que os primeiros 24 bits são para rede.'],
                    ['user' => 'Como sei se dois IPs pertencem à mesma rede?', 'confusion' => 0.8, 'frustration' => 0.6, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'Fazes a operação lógica AND bit a bit entre o endereço IP e a máscara. Se o resultado (endereço de rede) for idêntico para ambos, eles pertencem à mesma rede e comunicam sem router.']
                ]
            ],
            [
                'title' => '[DEMO] Broadcast e gateway',
                'topic' => 'gateways',
                'subtopic' => 'Configurações de Rede',
                'messages' => [
                    ['user' => 'O que é o gateway de uma rede?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O gateway é o endereço de saída de uma rede local para outras redes (geralmente a internet). Normalmente é o IP do router local.'],
                    ['user' => 'E o que é o endereço de broadcast?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'É um endereço especial usado para enviar dados para todos os hosts da rede em simultâneo (ex: 255.255.255.255 ou o último IP utilizável da sub-rede).'],
                    ['user' => 'O gateway tem de estar na mesma sub-rede?', 'confusion' => 0.7, 'frustration' => 0.4, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim! O gateway padrão tem obrigatoriamente de ter um IP que pertença à mesma sub-rede do dispositivo local, senão este não o consegue contactar.']
                ]
            ],
            [
                'title' => '[DEMO] Switches e routers',
                'topic' => 'switches vs routers',
                'subtopic' => 'Equipamentos de Rede',
                'messages' => [
                    ['user' => 'Qual é a diferença entre um switch e um router?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O switch conecta dispositivos dentro de uma mesma rede local (L2, baseado em MAC addresses). O router conecta diferentes redes (L3, baseado em endereços IP).'],
                    ['user' => 'Um switch de L3 pode fazer o papel de router?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim, switches L3 (Layer 3) podem realizar roteamento de pacotes IP além de comutação ethernet rápida.'],
                    ['user' => 'O router reduz domínios de broadcast?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim, de facto o router delimita os domínios de broadcast, impedindo que broadcasts locais de L2 passem para outras redes.']
                ]
            ],
            [
                'title' => '[DEMO] NAT e redes privadas',
                'topic' => 'NAT',
                'subtopic' => 'Tradução de Endereços',
                'messages' => [
                    ['user' => 'O que é NAT e porque é necessário?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'NAT (Network Address Translation) traduz endereços IP privados da rede local para um único IP público roteável, economizando endereços IPv4.'],
                    ['user' => 'Quais as desvantagens do NAT?', 'confusion' => 0.6, 'frustration' => 0.2, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'NAT introduz latência de processamento de pacotes, dificulta conexões diretas peer-to-peer e prejudica a rastreabilidade ponta-a-ponta.'],
                    ['user' => 'O que é o PAT?', 'confusion' => 0.8, 'frustration' => 0.5, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'neutral'],
                    ['ai' => 'PAT (Port Address Translation) é uma variação de NAT dinâmico onde múltiplos IPs privados são mapeados num único IP público recorrendo a portas TCP/UDP diferentes.']
                ]
            ],
            [
                'title' => '[DEMO] Protocolo DHCP',
                'topic' => 'DHCP',
                'subtopic' => 'Configuração Automática',
                'messages' => [
                    ['user' => 'Para que serve o DHCP nas redes locais?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O DHCP (Dynamic Host Configuration Protocol) atribui automaticamente configurações IP (IP, máscara, gateway, DNS) a novos dispositivos.'],
                    ['user' => 'Como funciona o processo de obtenção de IP?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Funciona através do ciclo DORA: Discover (Descoberta), Offer (Oferta), Request (Pedido), Acknowledge (Confirmação).'],
                    ['user' => 'O que acontece se houver dois servidores DHCP na rede?', 'confusion' => 0.9, 'frustration' => 0.8, 'urgency' => 0.85, 'priority' => 'critical', 'sentiment' => 'negative'],
                    ['ai' => 'Pode ocorrer um conflito de IPs (Rogue DHCP) se ambos atribuírem a mesma gama de IPs, originando instabilidade na rede. O cliente aceitará a primeira oferta que chegar.']
                ]
            ],
            [
                'title' => '[DEMO] Sockets de rede',
                'topic' => 'Sockets',
                'subtopic' => 'Programação de Rede',
                'messages' => [
                    ['user' => 'O que são sockets e como são usados?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Um socket é uma extremidade de um canal de comunicação bidirecional entre dois programas que correm na rede, definidos por IP + Porta.'],
                    ['user' => 'Como programar um socket TCP?', 'confusion' => 0.6, 'frustration' => 0.3, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'No fluxo clássico de socket TCP, o servidor cria, faz bind, listen e accept. O cliente cria e faz connect.'],
                    ['user' => 'O socket UDP usa listen?', 'confusion' => 0.8, 'frustration' => 0.6, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'Não! Como o UDP não é orientado a ligação, o socket não necessita de ligar-se previamente, usando apenas sendto() e recvfrom() para enviar e receber datagramas.']
                ]
            ],
            [
                'title' => '[DEMO] Fiabilidade no TCP',
                'topic' => 'TCP/IP',
                'subtopic' => 'Mecanismos de Transporte',
                'messages' => [
                    ['user' => 'Como é que o TCP garante fiabilidade na entrega?', 'confusion' => 0.5, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Através de números de sequência, confirmações de receção (ACKs), retransmissões automáticas por timeout e controlo de fluxo (janela deslizante).'],
                    ['user' => 'O que acontece se um segmento for perdido?', 'confusion' => 0.6, 'frustration' => 0.3, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'O recetor não envia o ACK correspondente, fazendo com que o temporizador do emissor expire e este retransmita o segmento perdido.'],
                    ['user' => 'O que é o fast retransmit?', 'confusion' => 0.8, 'frustration' => 0.5, 'urgency' => 0.7, 'priority' => 'high', 'sentiment' => 'neutral'],
                    ['ai' => 'É um mecanismo onde o emissor retransmite um pacote imediatamente após receber 3 ACKs duplicados, sem precisar de esperar que o temporizador de retransmissão expire.']
                ]
            ],
            [
                'title' => '[DEMO] Trabalho prático de redes',
                'topic' => 'routing',
                'subtopic' => 'Packet Tracer e OSPF',
                'messages' => [
                    ['user' => 'Como configurar rotas dinâmicas com OSPF no Packet Tracer?', 'confusion' => 0.7, 'frustration' => 0.4, 'urgency' => 0.6, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'Deves habilitar o processo ospf no router usando o comando "router ospf <process-id>" e depois declarar as redes conectadas via comando "network <ip> <wildcard-mask> area <area-id>".'],
                    ['user' => 'O que é a wildcard mask?', 'confusion' => 0.6, 'frustration' => 0.3, 'urgency' => 0.4, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'A wildcard mask é o inverso da máscara de rede. Por exemplo, a máscara 255.255.255.0 tem a wildcard 0.0.0.255.'],
                    ['user' => 'E como configuro a área no comando network?', 'confusion' => 0.8, 'frustration' => 0.7, 'urgency' => 0.75, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'Basta anexar "area <id_area>" ao final. Exemplo: "network 192.168.1.0 0.0.0.255 area 0" para colocares a interface correspondente na Área Backbone.']
                ]
            ],
            [
                'title' => '[DEMO] Resumo para teste',
                'topic' => 'Resumo para teste',
                'subtopic' => 'Preparação Escrita',
                'messages' => [
                    ['user' => 'Quais os temas mais importantes a rever para o teste escrito de Redes?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Foca no Modelo OSI (funções e protocolos das camadas L2, L3, L4 e L7), cálculo de subnetting (VLSM), diferenças de TCP/UDP e DHCP/DNS/NAT.'],
                    ['user' => 'Podes fazer perguntas de teste rápidas sobre subnetting?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Claro! Primeira pergunta: Qual é o endereço de rede para o IP 172.16.10.45/27?'],
                    ['user' => 'Como resolvo isso passo-a-passo?', 'confusion' => 0.85, 'frustration' => 0.6, 'urgency' => 0.8, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'A máscara /27 agrupa de 32 em 32 IPs. Os blocos começam em .0, .32, .64... Como o IP é .45, ele está na rede que começa em .32. Logo, a rede é 172.16.10.32/27.']
                ]
            ],
            [
                'title' => '[DEMO] Perguntas frequentes',
                'topic' => 'three-way handshake',
                'subtopic' => 'Controlo de Conexões TCP',
                'messages' => [
                    ['user' => 'Como funciona o three-way handshake do TCP?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'O estabelecimento de ligação TCP envolve três passos: 1. Cliente envia SYN; 2. Servidor responde com SYN-ACK; 3. Cliente envia ACK. A partir daí a ligação fica estabelecida.'],
                    ['user' => 'Qual o objetivo de usar números de sequência iniciais aleatórios?', 'confusion' => 0.5, 'frustration' => 0.2, 'urgency' => 0.3, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Previne ataques de hijacking de sessões, garantindo segurança na associação de pacotes à ligação activa.'],
                    ['user' => 'O que acontece se o SYN-ACK for perdido?', 'confusion' => 0.7, 'frustration' => 0.4, 'urgency' => 0.5, 'priority' => 'medium', 'sentiment' => 'neutral'],
                    ['ai' => 'O cliente não recebe a resposta e após um período de timeout retransmite o pacote SYN inicial ao servidor.']
                ]
            ],
            [
                'title' => '[DEMO] Revisão geral da UC',
                'topic' => 'switches vs routers',
                'subtopic' => 'Redes Locais L2 e L3',
                'messages' => [
                    ['user' => 'O que é um domínio de colisão e um domínio de broadcast?', 'confusion' => 0.4, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Domínio de colisão é o segmento de rede onde pacotes podem colidir (delimitado por switches). Domínio de broadcast é a rede onde pacotes de broadcast se propagam (delimitado por routers).'],
                    ['user' => 'O switch divide os domínios de colisão?', 'confusion' => 0.3, 'frustration' => 0.1, 'urgency' => 0.2, 'priority' => 'low', 'sentiment' => 'neutral'],
                    ['ai' => 'Sim! Cada porta de um switch é um domínio de colisão independente.'],
                    ['user' => 'E o hub divide domínios de colisão?', 'confusion' => 0.8, 'frustration' => 0.7, 'urgency' => 0.8, 'priority' => 'high', 'sentiment' => 'negative'],
                    ['ai' => 'Não! O hub partilha o mesmo meio de transmissão física, logo, todas as suas portas fazem parte de um único domínio de colisão.']
                ]
            ]
        ];

        // 7. Loop de Geração dos Chats, Mensagens e Análises com datas distribuídas
        foreach ($chatsData as $index => $chatInfo) {
            // Distribuir os últimos 30 dias
            $daysAgo = 30 - $index;
            $chatDate = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 12))->subMinutes(rand(1, 59));

            // Criar o Chat
            $chat = Chat::create([
                'user_id' => $student->id,
                'subject_id' => $subject->id,
                'title' => $chatInfo['title'],
                'context_type' => 'subject',
                'created_at' => $chatDate,
                'updated_at' => $chatDate,
            ]);

            $lastMsgDate = $chatDate;

            foreach ($chatInfo['messages'] as $msgIndex => $msg) {
                $role = isset($msg['user']) ? 'user' : 'ai';
                $content = isset($msg['user']) ? $msg['user'] : $msg['ai'];

                // Acrescentar tempo para as mensagens subsequentes
                $msgDate = $lastMsgDate->copy()->addSeconds(rand(10, 60));
                if ($msgIndex > 0) {
                    $msgDate = $lastMsgDate->copy()->addMinutes(rand(2, 5))->addSeconds(rand(5, 30));
                }

                // Criar a Mensagem
                $message = Message::create([
                    'chat_id' => $chat->id,
                    'role' => $role,
                    'content' => $content,
                    'created_at' => $msgDate,
                    'updated_at' => $msgDate,
                ]);

                $lastMsgDate = $msgDate;

                // 8. Se for mensagem de utilizador (user), criar a análise associada (para a dashboard de professor ler)
                if ($role === 'user') {
                    StudentMessageAnalysis::create([
                        'message_id' => $message->id,
                        'chat_id' => $chat->id,
                        'subject_id' => $subject->id,
                        'course_id' => $course->id,
                        'student_hash' => $studentHash,
                        'role' => 'user',
                        'language' => 'pt',
                        'question_excerpt' => Str::limit($content, 280),
                        'topic' => $chatInfo['topic'],
                        'subtopic' => $chatInfo['subtopic'],
                        'intent' => 'duvida',
                        'confusion_score' => $msg['confusion'],
                        'frustration_score' => $msg['frustration'],
                        'urgency_score' => $msg['urgency'],
                        'difficulty_level' => $msg['confusion'] >= 0.7 ? 'hard' : ($msg['confusion'] >= 0.4 ? 'medium' : 'easy'),
                        'priority' => $msg['priority'],
                        'sentiment' => $msg['sentiment'],
                        'is_recurring' => in_array($chatInfo['topic'], ['Subnetting', 'TCP/IP', 'switches vs routers', 'routing']),
                        'needs_teacher_attention' => $msg['frustration'] >= 0.6 || $msg['priority'] === 'critical',
                        'llm_summary' => "Dúvida do aluno sobre " . $chatInfo['topic'] . " (" . $chatInfo['subtopic'] . ")",
                        'suggested_teacher_action' => "Rever conceitos de " . $chatInfo['topic'] . " e reforçar exercícios de " . $chatInfo['subtopic'] . ".",
                        'analysis_provider' => 'rag',
                        'analysis_version' => '1.0',
                        'processed_at' => $msgDate,
                        'created_at' => $msgDate,
                        'updated_at' => $msgDate,
                    ]);
                }
            }

            // Atualizar o updated_at do chat para condizer com o da última mensagem
            $chat->update(['updated_at' => $lastMsgDate]);
        }

        $this->command->info('✅ Seeder de demonstração executado com sucesso!');
        $this->command->info('👨‍🎓 Conta Aluno: aluno.dashboard@tuts.test / password');
        $this->command->info('👨‍🏫 Conta Professor: professor.dashboard@tuts.test / password');
        $this->command->info('📚 Cadeira: Redes de Computadores (RC)');
        $this->command->info('💬 Conversas criadas: 20');
        $this->command->info('✉️ Mensagens criadas: 120 (60 user, 60 ai)');
        $this->command->info('📊 Análises criadas: 60');
    }
}
