<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;

// 投稿関連
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);   // 投稿詳細
Route::post('/posts', [PostController::class, 'store']);      // 投稿追加
Route::delete('/posts/{id}', [PostController::class, 'destroy']); // 投稿削除

// いいね関連
Route::post('/posts/{post}/like', [PostController::class, 'like']);
Route::delete('/posts/{post}/like', [PostController::class, 'unlike']);

// コメント関連
Route::get('/posts/{post}/comments', [CommentController::class, 'index']); // コメント一覧
Route::post('/posts/{post}/comments', [CommentController::class, 'store']); // コメント追加
Route::delete('/comments/{comment}', [CommentController::class, 'destroy']); // コメント削除