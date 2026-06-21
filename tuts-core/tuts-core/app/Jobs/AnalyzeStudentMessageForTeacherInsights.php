<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\StudentMessageAnalysis;
use App\Services\TeacherInsightAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeStudentMessageForTeacherInsights implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $messageId;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function handle(TeacherInsightAnalysisService $analysisService)
    {
        Log::info("[TeacherInsightsJob] Starting analysis for message {$this->messageId}");

        $message = Message::with('chat.user')->find($this->messageId);

        if (!$message) {
            Log::warning("[TeacherInsightsJob] Message {$this->messageId} not found. Skipping.");
            return;
        }

        if ($message->role !== 'user') {
            Log::info("[TeacherInsightsJob] Message {$this->messageId} is not a student message. Skipping.");
            return;
        }

        $chat = $message->chat;
        if (!$chat) {
            Log::warning("[TeacherInsightsJob] Chat for message {$this->messageId} not found. Skipping.");
            return;
        }

        if (!$chat->subject_id) {
            Log::info("[TeacherInsightsJob] Chat {$chat->id} has no associated subject. Skipping.");
            return;
        }

        // Generate student hash for privacy (irreversible HMAC of user ID)
        $studentHash = null;
        if ($chat->user_id) {
            $studentHash = hash_hmac('sha256', (string) $chat->user_id, config('app.key'));
        }

        try {
            $analysisService->analyze($message, $chat, $studentHash);
            Log::info("[TeacherInsightsJob] Analysis completed successfully for message {$this->messageId}");
        } catch (\Exception $e) {
            Log::error("[TeacherInsightsJob] Error analyzing message {$this->messageId}: " . $e->getMessage());
            throw $e;
        }
    }
}
