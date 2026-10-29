<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Private channel authorization for token clients (desktop). The web uses /broadcasting/auth
 * with its session. The response is the raw Pusher {"auth": ...} body that Echo expects, not
 * the {"data": ...} envelope.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}