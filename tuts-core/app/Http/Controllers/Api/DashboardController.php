<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function getMetrics()
    {
        // 1. Defesa em Profundidade (Autorização)
        // O middleware nas rotas já protege, mas trancamos aqui também
        // para garantir que se a rota mudar, a segurança mantém-se.
        Gate::authorize('view-dashboard');

        // 2. Sistema de Cache (Prevenção de DoS e carga pesada na BD)
        // Agregamos as métricas globais e guardamos por 30 minutos.
        $metrics = Cache::remember('dashboard_metrics_global', now()->addMinutes(30), function () {
            $topicosCount = [];
            $frustracaoTotal = 0;
            $totalAnalisadas = 0;

            Message::whereNotNull('meta_data')
                ->select('id', 'meta_data')
                ->orderBy('id')
                ->chunkById(500, function ($messages) use (&$topicosCount, &$frustracaoTotal, &$totalAnalisadas) {
                    foreach ($messages as $msg) {
                        $meta = $msg->meta_data;
                        $totalAnalisadas++;

                        if (isset($meta['frustracao'])) {
                            // Garantir que é um inteiro seguro entre limites lógicos
                            $frustracao = (int) $meta['frustracao'];
                            $frustracaoTotal += min(max($frustracao, 0), 10);
                        }

                        if (isset($meta['topicos']) && is_array($meta['topicos'])) {
                            foreach ($meta['topicos'] as $topico) {
                                // 3. Sanitização Rigorosa contra Stored XSS e Poluição de Dados
                                // Removemos tags HTML completamente
                                $topicoLimpo = strip_tags((string) $topico);

                                // Aceitamos apenas letras, números, espaços, hífens e underscores
                                $topicoLimpo = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $topicoLimpo);

                                // Limitamos a string a 50 caracteres para evitar que quebre o layout
                                $topicoLimpo = Str::limit(trim($topicoLimpo), 50, '');

                                // Só contabiliza se ainda sobrar alguma coisa útil após a limpeza
                                if (!empty($topicoLimpo)) {
                                    if (!isset($topicosCount[$topicoLimpo])) {
                                        $topicosCount[$topicoLimpo] = 0;
                                    }
                                    $topicosCount[$topicoLimpo]++;
                                }
                            }
                        }
                    }
                });

            arsort($topicosCount);

            return [
                'total_analisadas' => $totalAnalisadas,
                'media_frustracao' => $totalAnalisadas > 0 ? round($frustracaoTotal / $totalAnalisadas, 1) : 0,
                'topicos'          => array_slice($topicosCount, 0, 5, true),
            ];
        });

        return response()->json($metrics);
    }
}
