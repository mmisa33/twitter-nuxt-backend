<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

class FirebaseAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $idToken = substr($header, 7);

        try {
            $auth = (new Factory)
                ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                ->createAuth();

            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');

            $user = \App\Models\User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'name' => 'FirebaseUser',
                    'email' => $uid . '@firebase.local',
                ]
            );

            auth()->login($user);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid token', 'message' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}
