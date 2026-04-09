<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getMetrics()
    {
        // Vai buscar todas as mensagens que já foram analisadas pela IA
        $messages = Message::whereNotNull('meta_data')->get();

        $topicosCount = [];
        $frustracaoTotal = 0;
        $totalAnalisadas = $messages->count();

        foreach ($messages as $msg) {
            $meta = $msg->meta_data;

            // 1. Somar a frustração
            if (isset($meta['frustracao'])) {
                $frustracaoTotal += $meta['frustracao'];
            }

            // 2. Contar os tópicos de dúvida
            if (isset($meta['topicos']) && is_array($meta['topicos'])) {
                foreach ($meta['topicos'] as $topico) {
                    if (!isset($topicosCount[$topico])) {
                        $topicosCount[$topico] = 0;
                    }
                    $topicosCount[$topico]++;
                }
            }
        }

        // Ordenar os tópicos do mais problemático para o menos
        arsort($topicosCount);

        return response()->json([
            'total_analisadas' => $totalAnalisadas,
            'media_frustracao' => $totalAnalisadas > 0 ? round($frustracaoTotal / $totalAnalisadas, 1) : 0,
            // Devolve apenas os 5 tópicos com mais dúvidas para o gráfico não ficar confuso
            'topicos' => array_slice($topicosCount, 0, 5)
        ]);
    }
}
