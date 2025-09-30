<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\User;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $idTokenString = $request->bearerToken();
        $currentUserId = null;

        if ($idTokenString) {
            try {
                $firebaseAuth = (new \Kreait\Firebase\Factory)
                    ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                    ->createAuth();

                $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
                $firebaseUid = $verifiedIdToken->claims()->get('sub');

                $user = User::where('firebase_uid', $firebaseUid)->first();
                if ($user) $currentUserId = $user->id;
            } catch (\Throwable $e) {
                \Log::error('Firebase認証エラー: ' . $e->getMessage());
            }
        }

        $posts = Post::with(['user', 'likes'])->latest()->get()->map(function ($post) use ($currentUserId) {
            $likeCount = $post->likes->count();
            return [
                'id' => $post->id,
                'user' => $post->user,
                'content' => $post->content,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'liked_by_current_user' => $currentUserId ? $post->likes->contains('user_id', $currentUserId) : false,
                'like_count' => $likeCount > 0 ? $likeCount : null, // 0の場合はnull
            ];
        });

        return response()->json($posts);
    }

    public function show(Request $request, $id)
    {
        try {
            $idTokenString = $request->bearerToken();
            $currentUserId = null;

            if ($idTokenString) {
                try {
                    $firebaseAuth = (new Factory)
                        ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                        ->createAuth();

                    $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
                    $firebaseUid = $verifiedIdToken->claims()->get('sub');

                    $user = User::where('firebase_uid', $firebaseUid)->first();
                    if ($user) $currentUserId = $user->id;
                } catch (\Throwable $e) {
                    \Log::error('Firebase認証エラー(show): ' . $e->getMessage());
                }
            }

            $post = Post::with(['user', 'likes', 'comments.user'])->findOrFail($id);

            $postArray = [
                'id' => $post->id,
                'user' => $post->user,
                'content' => $post->content,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'like_count' => $post->likes->count(),
                'liked_by_current_user' => $currentUserId
                    ? $post->likes->contains('user_id', $currentUserId)
                    : false,
            ];

            $commentsArray = $post->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user' => $comment->user,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                ];
            });

            return response()->json([
                'post' => $postArray,
                'comments' => $commentsArray,
            ]);
        } catch (\Throwable $e) {
            \Log::error('投稿詳細取得エラー: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => '投稿詳細取得中に問題が発生しました'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // 入力バリデーション
        $request->validate([
            'content' => 'required|string|max:120',
        ]);

        $idTokenString = $request->bearerToken();

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                ->createAuth();

            // Firebase トークン検証
            $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            // Firebase ユーザー情報取得
            $firebaseUser = $firebaseAuth->getUser($firebaseUid);

            // ユーザー取得または作成
            $user = User::firstOrCreate(
                ['firebase_uid' => $firebaseUid],
                [
                    'name'  => $firebaseUser->displayName ?? '仮名',
                    'email' => $firebaseUser->email ?? '',
                    'password' => bcrypt('dummy_password')
                ]
            );

            // 投稿作成
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $request->content,
            ]);

            // 投稿者情報をロードして返す
            $post->load('user');

            return response()->json($post, 201);
        } catch (\Throwable $e) {
            // ログには詳細を残す
            Log::error('投稿作成エラー: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            // ユーザーには一般的なエラーを返す
            return response()->json([
                'error' => 'Server error',
                'message' => '投稿処理中に問題が発生しました。'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $idTokenString = $request->bearerToken();

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(base_path('firebase/firebase-credentials.json'))
                ->createAuth();

            $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $firebaseUid)->firstOrFail();
            $post = Post::findOrFail($id);

            // 権限チェック
            if ($post->user_id !== $user->id) {
                return response()->json(['message' => '権限がありません'], 403);
            }

            $post->delete();
            return response()->json(['message' => '削除しました']);
        } catch (\Throwable $e) {
            Log::error('投稿削除エラー: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'error' => 'Server error',
                'message' => '削除処理中に問題が発生しました。'
            ], 500);
        }
    }

    public function like(Request $request, $id)
    {
        $idTokenString = $request->bearerToken();
        $firebaseAuth = (new Factory)->withServiceAccount(base_path('firebase/firebase-credentials.json'))->createAuth();
        $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
        $firebaseUid = $verifiedIdToken->claims()->get('sub');

        $user = User::firstOrCreate(
            ['firebase_uid' => $firebaseUid],
            ['name' => '仮名', 'email' => '', 'password' => bcrypt('dummy_password')]
        );

        $post = Post::findOrFail($id);
        $post->likes()->firstOrCreate(['user_id' => $user->id]);

        return response()->json([
            'likes_count' => $post->likes()->count(),
            'liked_by_current_user' => true, // 追加
        ]);
    }

    public function unlike(Request $request, $id)
    {
        $idTokenString = $request->bearerToken();
        $firebaseAuth = (new Factory)->withServiceAccount(base_path('firebase/firebase-credentials.json'))->createAuth();
        $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
        $firebaseUid = $verifiedIdToken->claims()->get('sub');

        $user = User::where('firebase_uid', $firebaseUid)->firstOrFail();
        $post = Post::findOrFail($id);

        $post->likes()->where('user_id', $user->id)->delete();

        return response()->json([
            'likes_count' => $post->likes()->count(),
            'liked_by_current_user' => false, // 追加
        ]);
    }
}
