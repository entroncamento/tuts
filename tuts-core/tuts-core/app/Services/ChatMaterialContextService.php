<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatMaterialContext;
use App\Models\Message;
use App\Models\PersonalMaterial;
use App\Models\SubjectMaterial;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ChatMaterialContextService
{
    /**
     * List active and non-expired material contexts for a chat.
     */
    public function listActiveForChat(Chat $chat)
    {
        return ChatMaterialContext::query()
            ->forChat($chat)
            ->active()
            ->notExpired()
            ->get();
    }

    /**
     * Activate a personal material context for a chat.
     *
     * Personal material can only be activated if personal_material.owner_id equals chat.user_id.
     */
    public function activatePersonalMaterial(Chat $chat, PersonalMaterial $material, ?Message $message = null): ChatMaterialContext
    {
        $ownerId = $material->owner_id ?? $material->user_id;

        if ((int) $ownerId !== (int) $chat->user_id) {
            Log::warning('[TUTS][ChatMaterialContext] Attempted to activate personal material for non-owner', [
                'chat_id' => $chat->id,
                'user_id' => $chat->user_id,
                'material_id' => $material->id,
                'owner_id' => $ownerId,
            ]);
            throw new InvalidArgumentException('Personal material owner does not match chat user.');
        }

        Log::info('[TUTS][ChatMaterialContext] Activating personal material context', [
            'chat_id' => $chat->id,
            'user_id' => $chat->user_id,
            'personal_material_id' => $material->id,
            'message_id' => $message?->id,
        ]);

        return ChatMaterialContext::updateOrCreate(
            [
                'chat_id' => $chat->id,
                'source' => ChatMaterialContext::SOURCE_PERSONAL,
                'personal_material_id' => $material->id,
            ],
            [
                'user_id' => $chat->user_id,
                'added_from_message_id' => $message?->id,
                'active' => true,
                'expires_at' => null,
            ]
        );
    }

    /**
     * Activate a subject material context for a chat.
     */
    public function activateSubjectMaterial(Chat $chat, SubjectMaterial $material, ?Message $message = null): ChatMaterialContext
    {
        Log::info('[TUTS][ChatMaterialContext] Activating subject material context', [
            'chat_id' => $chat->id,
            'user_id' => $chat->user_id,
            'subject_material_id' => $material->id,
            'subject_id' => $material->subject_id,
            'message_id' => $message?->id,
        ]);

        return ChatMaterialContext::updateOrCreate(
            [
                'chat_id' => $chat->id,
                'source' => ChatMaterialContext::SOURCE_SUBJECT,
                'subject_material_id' => $material->id,
            ],
            [
                'user_id' => $chat->user_id,
                'subject_id' => $material->subject_id,
                'added_from_message_id' => $message?->id,
                'active' => true,
                'expires_at' => null,
            ]
        );
    }

    /**
     * Deactivate a material context for a chat.
     */
    public function deactivateForChatMaterial(Chat $chat, string $source, int $materialId): bool
    {
        Log::info('[TUTS][ChatMaterialContext] Deactivating material context', [
            'chat_id' => $chat->id,
            'source' => $source,
            'material_id' => $materialId,
        ]);

        $query = ChatMaterialContext::query()
            ->where('chat_id', $chat->id)
            ->where('source', $source);

        if ($source === ChatMaterialContext::SOURCE_PERSONAL) {
            $query->where('personal_material_id', $materialId);
        } elseif ($source === ChatMaterialContext::SOURCE_SUBJECT) {
            $query->where('subject_material_id', $materialId);
        } else {
            Log::warning('[TUTS][ChatMaterialContext] Unknown source type for deactivation', [
                'source' => $source,
            ]);
            return false;
        }

        $context = $query->first();

        if ($context) {
            return $context->update(['active' => false]);
        }

        return false;
    }
}
