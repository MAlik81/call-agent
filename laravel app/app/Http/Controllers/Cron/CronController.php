<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    /** Simple bearer check to protect cron endpoints */
    private function assertBearer(Request $request): void
    {
        $shared = config('app.shared_token', env('APP_SHARED_TOKEN'));

        if ($request->bearerToken() !== $shared) {
            abort(401, 'Unauthorized');
        }
    }

    /** Trigger the pending call segments STT processor via HTTP */
    public function processCallSegments(Request $request)
    {
        $this->assertBearer($request);

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $params = [];

        if (!empty($data['limit'])) {
            $params['--limit'] = (int) $data['limit'];
        }

        $exitCode = Artisan::call('app:process-call-segments', $params);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }
}
