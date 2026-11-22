<?php

namespace App\Mobile\Http;

use App\Mobile\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Stores FCM tokens per user. Push delivery (FCM HTTP v1) is a later integration; see FcmSender. */
class DeviceController extends Controller
{
    public function store(Request $r)
    {
        $d = $r->validate(['token' => ['required', 'string', 'max:255'], 'platform' => ['sometimes', 'in:android,ios,web']]);
        DeviceToken::updateOrCreate(['token' => $d['token']], ['user_id' => $r->user()->id, 'platform' => $d['platform'] ?? 'android']);

        return response()->json(['message' => 'Registered.'], 201);
    }

    public function destroy(Request $r)
    {
        DeviceToken::where('token', (string) $r->input('token'))->where('user_id', $r->user()->id)->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
