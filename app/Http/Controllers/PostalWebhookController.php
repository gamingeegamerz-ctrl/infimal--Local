<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SMTPAccount;
use Illuminate\Support\Facades\Log;

class PostalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->input('event');
        $payload = $request->input('payload', []);

        if (!isset($payload['credential_id'])) {
            return response()->json(['ignored' => true]);
        }

        $smtp = SMTPAccount::find($payload['credential_id']);

        if (!$smtp) {
            return response()->json(['ignored' => true]);
        }

        $penalty = match ($event) {
            'MessageHardBounce'     => 10,
            'MessageSpamComplaint' => 25,
            'MessageSoftBounce'     => 3,
            default                => 0,
        };

        if ($penalty === 0) {
            return response()->json(['ignored' => true]);
        }

        $smtp->reputation_score = max(0, $smtp->reputation_score - $penalty);

        if ($smtp->reputation_score < 40) {
            $smtp->is_active = false;
        }

        $smtp->save();

        Log::info('Postal webhook processed', [
            'event' => $event,
            'smtp_id' => $smtp->id,
            'new_score' => $smtp->reputation_score,
            'disabled' => !$smtp->is_active,
        ]);

        return response()->json([
            'status' => 'processed',
            'event' => $event,
            'smtp_id' => $smtp->id,
            'reputation' => $smtp->reputation_score,
        ]);
    }
}
