<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CallSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BotAppointmentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'call_session_id' => 'required|integer|exists:call_sessions,id',
            'intent' => 'required|string',
            'status' => 'required|string',
            'slots' => 'required|array',
        ]);

        $session = CallSession::find($data['call_session_id']);

        $startAt = null;
        $endAt = null;
        if (!empty($data['slots']['date']) && !empty($data['slots']['time'])) {
            try {
                $timezone = $data['slots']['timezone'] ?? config('app.timezone');
                $startAt = Carbon::parse($data['slots']['date'] . ' ' . $data['slots']['time'], $timezone);
                $endAt = (clone $startAt)->addHour();
            } catch (\Throwable $e) {
                Log::warning('Failed to parse appointment time', ['slots' => $data['slots']]);
            }
        }

        $appointment = Appointment::create([
            'tenant_id' => $session->tenant_id,
            'call_session_id' => $session->id,
            'service' => $data['slots']['service'] ?? null,
            'client_name' => $data['slots']['customer_name'] ?? null,
            'client_phone' => $data['slots']['customer_phone'] ?? null,
            'client_email' => null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => $data['slots']['timezone'] ?? null,
            'duration_minutes' => $startAt && $endAt ? $endAt->diffInMinutes($startAt) : null,
            'status' => 'confirmed',
            'meta' => [
                'intent' => $data['intent'],
                'status' => $data['status'],
                'slots' => $data['slots'],
            ],
        ]);

        return response()->json([
            'ok' => true,
            'appointment_id' => $appointment->id,
        ]);
    }
}
