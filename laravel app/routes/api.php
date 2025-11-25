<?php

use Illuminate\Support\Facades\Route;



use App\Http\Controllers\Telephony\TwilioCallControlController;
use App\Http\Controllers\Turns\TurnIngestController; // from prev step
use App\Http\Controllers\Telephony\TwilioVoiceWebhookController;
use App\Http\Controllers\Telephony\VoiceBootstrapController;
use App\Http\Controllers\CallSegmentController;
use App\Http\Controllers\Cron\CronController;



Route::get('/health',        [TurnIngestController::class, 'health']);
Route::post('/turns/ingest', [TurnIngestController::class, 'ingest']);

Route::get('/ws-health', [TurnIngestController::class, 'wsHealth']);

Route::post('/voice/bootstrap', [VoiceBootstrapController::class, 'bootstrap']);

Route::post('/cron/process-call-segments', [CronController::class, 'processCallSegments']);


// new
Route::post('/twilio/calls/play', [TwilioCallControlController::class, 'play']);
Route::post('/twilio/calls/stop', [TwilioCallControlController::class, 'stop']);

Route::post('/call-sessions/{call}/segments', [CallSegmentController::class, 'store']);



Route::post('/twilio/voice/incoming', [TwilioVoiceWebhookController::class, 'incoming']);


Route::post('/payments-providers/stripe/webhook', [
    App\Http\Controllers\PaymentProviders\StripeController::class,
    'handleWebhook',
])->name('payments-providers.stripe.webhook');

Route::post('/payments-providers/paddle/webhook', [
    App\Http\Controllers\PaymentProviders\PaddleController::class,
    'handleWebhook',
])->name('payments-providers.paddle.webhook');

Route::post('/payments-providers/lemon-squeezy/webhook', [
    App\Http\Controllers\PaymentProviders\LemonSqueezyController::class,
    'handleWebhook',
])->name('payments-providers.lemon-squeezy.webhook');
