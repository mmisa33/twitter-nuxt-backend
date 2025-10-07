# Twitter風SNSアプリ（バックエンド）

## 概要
このリポジトリは、Twitter風SNSアプリのバックエンドAPIを管理しています。  
Laravelを使用し、投稿・コメント・いいね・ユーザー情報の管理を行います。  
ユーザー認証はFirebase Authenticationを利用しており、バックエンドでは認証済みユーザーIDを受け取り、データ操作を行います。

## 環境構築

1. リポジトリをクローン
   ```bash
   git clone git@github.com:mmisa33/twitter-nuxt-backend.git
   ```
2. プロジェクトフォルダに移動
    ```bash
    cd twitter-nuxt-backend
    ```
3. 依存関係をインストール
    ```bash
    composer install
    ```
4. .env.example ファイルから .env を作成
    ```bash
    cp .env.example .env
    ```
5. .env ファイルのデータベース情報を自分の環境に合わせて変更
    ```bash
    (例)
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=twitter_nuxt
    DB_USERNAME=laraveluser
    DB_PASSWORD=password
    ```
6. アプリケーションキー生成
    ```bash
   php artisan key:generate
    ```
7. データベースをマイグレーション
    ```bash
    php artisan migrate
    ```
8. 開発サーバーを起動
    ```bash
    php artisan serve
    ```
    - デフォルトURL: http://127.0.0.1:8000
    - サーバー停止: Ctrl + C

## 主なAPI一覧
| 機能           | メソッド | エンドポイント                  |
|----------------|---------|--------------------------------|
| 投稿一覧取得     | GET     | /api/posts                     |
| 投稿詳細取得     | GET     | /api/posts/{id}                |
| 投稿作成         | POST    | /api/posts                     |
| 投稿削除         | DELETE  | /api/posts/{id}                |
| いいね追加       | POST    | /api/posts/{post}/like         |
| いいね削除       | DELETE  | /api/posts/{post}/like         |
| コメント一覧取得 | GET     | /api/posts/{post}/comments     |
| コメント追加     | POST    | /api/posts/{post}/comments     |
| コメント削除     | DELETE  | /api/comments/{comment}        |


- すべてのAPIは認証済みユーザーIDで動作  
- バリデーションはフロントとバック両方で実施  

## 使用技術
- PHP 8.4.4
- Laravel 10.48.29
- MySQL 8.0.43
- Firebase Authentication（ユーザー認証機能）

## 補足
- バックエンドではFirebaseのIDトークンを確認するのみで、ユーザー作成は行いません。  
- 投稿やコメントとの紐付けなど、ユーザー情報の管理はLaravel側で処理します。  
-  開発中は、ローカルサーバーとNuxtフロントエンドを同時に起動して動作確認を行います。  