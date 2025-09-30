<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    /**
     * コメント一覧取得
     */
    public function index(Post $post, Request $request)
    {
        $comments = $post->comments()->with('user')->latest()->get()->map(function ($comment) {
            return [
                'id' => $comment->id,
                'user' => $comment->user,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
            ];
        });

        return response()->json($comments);
    }

    /**
     * コメント追加
     */
    public function store(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:255',
        ]);

        $idTokenString = $request->bearerToken();
        if (!$idTokenString) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                ->createAuth();

            $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            // Firebase UID に対応するユーザーを取得または作成
            $user = User::firstOrCreate(
                ['firebase_uid' => $firebaseUid],
                [
                    'name' => '仮名',
                    'email' => '',
                    'password' => bcrypt('dummy_password')
                ]
            );

            $comment = $post->comments()->create([
                'user_id' => $user->id,
                'content' => $request->content,
            ]);

            $comment->load('user');

            return response()->json($comment, 201);
        } catch (\Throwable $e) {
            Log::error('コメント追加エラー: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json([
                'error' => 'Server error',
                'message' => 'コメント追加中に問題が発生しました'
            ], 500);
        }
    }

    /**
     * コメント削除
     */
    public function destroy(Request $request, Comment $comment)
    {
        $idTokenString = $request->bearerToken();
        if (!$idTokenString) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                ->createAuth();

            $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $firebaseUid)->firstOrFail();

            if ($comment->user_id !== $user->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $comment->delete();

            return response()->json(['message' => 'Deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('コメント削除エラー: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json([
                'error' => 'Server error',
                'message' => '削除中に問題が発生しました'
            ], 500);
        }
    }
}
