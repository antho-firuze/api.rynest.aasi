<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;
use Webman\Route;

Route::any('/', function () {
    return json(["message" => "Welcome to Rynest API Server !"]);
});

Route::group('/api/v1/auth', function () {
    Route::any('/', [app\api\Auth_v1::class, 'index']);
    Route::post('/signin', [app\api\Auth_v1::class, 'signin']);
    Route::post('/signup', [app\api\Auth_v1::class, 'signup']);
    Route::post('/send_forgot_code', [app\api\Auth_v1::class, 'send_forgot_code']);
    Route::post('/send_verification_code', [app\api\Auth_v1::class, 'send_verification_code']);
    Route::post('/confirm_verification_code', [app\api\Auth_v1::class, 'confirm_verification_code']);
    Route::post('/reset_pwd', [app\api\Auth_v1::class, 'reset_pwd']);
    Route::post('/change_pwd', [app\api\Auth_v1::class, 'change_pwd']);
    Route::post('/refresh_token', [app\api\Auth_v1::class, 'refresh_token']);
    Route::post('/closing_account', [app\api\Auth_v1::class, 'closing_account']);
})->middleware([
    app\middleware\VerifyAPIToken::class,
]);

Route::group('/api/v1/admin', function () {
    Route::any('/', [app\api\Admin_v1::class, 'index']);
    Route::post('/user', [app\api\Admin_v1::class, 'user']);
    Route::post('/member', [app\api\Admin_v1::class, 'member']);
    Route::post('/update_schedule', [app\api\Admin_v1::class, 'update_schedule']);
    Route::post('/clear_exam', [app\api\Admin_v1::class, 'clear_exam']);
})->middleware([
    app\middleware\VerifyAPIToken::class,
]);

Route::group('/api/v1/member', function () {
    Route::any ('/', [app\api\Member_v1::class, 'index']);
    Route::post('/profile', [app\api\Member_v1::class, 'profile']);
    Route::post('/certificate', [app\api\Member_v1::class, 'certificate']);
    Route::post('/upload_photo', [app\api\Member_v1::class, 'upload_photo']);
})->middleware([
    app\middleware\VerifyAPIToken::class,
]);

Route::group('/api/v1/exam', function () {
    Route::any('/', [app\api\Exam_v1::class, 'index']);
    Route::post('/schedule', [app\api\Exam_v1::class, 'schedule']);
    Route::post('/result', [app\api\Exam_v1::class, 'result']);
    Route::post('/info', [app\api\Exam_v1::class, 'info']);
    Route::post('/start', [app\api\Exam_v1::class, 'start']);
    Route::post('/answer', [app\api\Exam_v1::class, 'answer']);
    Route::post('/check_score', [app\api\Exam_v1::class, 'check_score']);
    Route::post('/finish', [app\api\Exam_v1::class, 'finish']);
    Route::post('/question', [app\api\Exam_v1::class, 'question']);
    Route::post('/questions', [app\api\Exam_v1::class, 'questions']);
    Route::post('/photos', [app\api\Exam_v1::class, 'photos']);
    Route::post('/upload_photo', [app\api\Exam_v1::class, 'upload_photo']);
})->middleware([
    app\middleware\VerifyAPIToken::class,
]);

Route::group('/api/v1/pusher', function () {
    Route::any('/', [app\api\Pusher_v1::class, 'index']);
    Route::post('/auth', [app\api\Pusher_v1::class, 'auth']);
    Route::post('/trigger', [app\api\Pusher_v1::class, 'trigger']);
    Route::post('/channels', [app\api\Pusher_v1::class, 'channels']);
    Route::post('/channel_info', [app\api\Pusher_v1::class, 'channel_info']);
    Route::post('/channel_info_users', [app\api\Pusher_v1::class, 'channel_info_users']);
})->middleware([
    app\middleware\VerifyAPIToken::class,
]);

Route::fallback(function (Request $request) {
    // Return JSON for AJAX requests
    $isTypeFormData = false !== strpos($request->header('Content-Type', ''), 'form-data');
    $isTypeAppJson = false !== strpos($request->header('Content-Type', ''), 'json');
    // return json($isTypeAppJson);
    return jsonr(['message' => '404 not found'], 404);

    if ($request->expectsJson() || $isTypeFormData || $isTypeAppJson) {
        return jsonr(['message' => '404 not found'], 404);
    }
    // Return the 404.html template for page requests
    return view('404', ['error' => 'some error'])->withStatus(404);
});