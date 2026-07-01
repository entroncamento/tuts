<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatMaterialContext;
use Illuminate\Support\Facades\Log;

class ChatRetrievalPlanBuilder
{
    /**
     * Build the retrieval plan for a given chat.
     *
     * @param Chat $chat
     * @return array
     */
    public function buildForChat(Chat $chat): array
    {
        $chatId = (int) $chat->id;
        $userId = (int) $chat->user_id;

        // 1. Infer base context
        $baseContext = $this->inferBaseContext($chat);

        // 2. Load active material contexts
        $activeMaterialContexts = $chat->activeMaterialContexts()->get();
        $activeMaterials = [];
        $skippedCount = 0;

        foreach ($activeMaterialContexts as $context) {
            if ($context->source === ChatMaterialContext::SOURCE_PERSONAL) {
                // Security check: ensure the personal context's user matches the chat user.
                if ((int) $context->user_id !== $userId) {
                    Log::warning('[TUTS][ChatRetrievalPlan] Mismatched user_id for personal material context', [
                        'chat_id' => $chatId,
                        'chat_user_id' => $userId,
                        'context_id' => $context->id,
                        'context_user_id' => $context->user_id,
                    ]);
                    $skippedCount++;
                    continue;
                }

                $activeMaterials[] = [
                    'source' => 'personal',
                    'personal_material_id' => (int) $context->personal_material_id,
                    'user_id' => (int) $context->user_id,
                    'active' => true,
                ];
            } elseif ($context->source === ChatMaterialContext::SOURCE_SUBJECT) {
                $activeMaterials[] = [
                    'source' => 'subject',
                    'subject_material_id' => (int) $context->subject_material_id,
                    'subject_id' => $context->subject_id ? (int) $context->subject_id : null,
                    'active' => true,
                ];
            } else {
                Log::info('[TUTS][ChatRetrievalPlan] Unsupported material context source skipped', [
                    'chat_id' => $chatId,
                    'context_id' => $context->id,
                    'source' => $context->source,
                ]);
                $skippedCount++;
            }
        }

        // 3. Log plan statistics safely
        Log::info('[TUTS][ChatRetrievalPlan] Built retrieval plan', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'context_type' => $chat->context_type,
            'base_context_type' => $baseContext['type'],
            'base_subject_id' => $baseContext['subject_id'],
            'base_section_id' => $baseContext['section_id'],
            'base_space_id' => $baseContext['space_id'],
            'base_folder_id' => $baseContext['folder_id'] ?? null,
            'active_material_count' => count($activeMaterials),
            'skipped_count' => $skippedCount,
        ]);

        return [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'context_type' => $chat->context_type,
            'base_context' => $baseContext,
            'active_materials' => $activeMaterials,
        ];
    }

    /**
     * Infer the base context from the Chat model characteristics.
     *
     * @param Chat $chat
     * @return array
     */
    private function inferBaseContext(Chat $chat): array
    {
        if ($chat->context_type === 'space' || $chat->study_space_id !== null) {
            return [
                'type' => 'space',
                'subject_id' => null,
                'section_id' => null,
                'space_id' => $chat->study_space_id ? (int) $chat->study_space_id : null,
                'folder_id' => $chat->space_folder_id ? (int) $chat->space_folder_id : null,
            ];
        }

        if ($chat->section_id !== null) {
            return [
                'type' => 'section',
                'subject_id' => $chat->subject_id ? (int) $chat->subject_id : null,
                'section_id' => (int) $chat->section_id,
                'space_id' => null,
            ];
        }

        if ($chat->context_type === 'uc' || $chat->subject_id !== null) {
            return [
                'type' => 'subject',
                'subject_id' => $chat->subject_id ? (int) $chat->subject_id : null,
                'section_id' => null,
                'space_id' => null,
            ];
        }

        if ($chat->context_type === 'temporary' || $chat->isTemporary()) {
            return [
                'type' => 'temporary',
                'subject_id' => null,
                'section_id' => null,
                'space_id' => null,
            ];
        }

        return [
            'type' => 'none',
            'subject_id' => null,
            'section_id' => null,
            'space_id' => null,
        ];
    }
}
