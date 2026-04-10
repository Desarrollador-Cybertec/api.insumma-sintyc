<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Public ──
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// ── Protected ──
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users
    Route::apiResource('users', UserController::class)->except(['destroy']);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
    Route::patch('/users/{user}/password', [UserController::class, 'updatePassword']);
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);

    // Areas
    Route::apiResource('areas', AreaController::class)->except(['destroy']);
    Route::delete('/areas/{area}', [AreaController::class, 'destroy']);
    Route::patch('/areas/{area}/manager', [AreaController::class, 'assignManager']);
    Route::post('/areas/claim-worker', [AreaController::class, 'claimWorker']);
    Route::get('/areas/{area}/available-workers', [AreaController::class, 'availableWorkers']);
    Route::get('/areas/{area}/members', [AreaController::class, 'members']);
    Route::delete('/areas/{area}/members/{user}', [AreaController::class, 'removeMember']);

    // Meetings
    Route::apiResource('meetings', MeetingController::class);
    Route::post('/meetings/{meeting}/tasks', [MeetingController::class, 'storeTasks']);
    Route::patch('/meetings/{meeting}/close', [MeetingController::class, 'close']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{task}/claim', [TaskController::class, 'claim']);
    Route::post('/tasks/{task}/delegate', [TaskController::class, 'delegate']);
    Route::post('/tasks/{task}/start', [TaskController::class, 'start']);
    Route::post('/tasks/{task}/submit-review', [TaskController::class, 'submitForReview']);
    Route::post('/tasks/{task}/approve', [TaskController::class, 'approve']);
    Route::post('/tasks/{task}/reject', [TaskController::class, 'reject']);
    Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel']);
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen']);
    Route::post('/tasks/{task}/comment', [TaskController::class, 'comment']);
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'addAttachment']);
    Route::post('/tasks/{task}/updates', [TaskController::class, 'addUpdate']);

    // Dashboard
    Route::get('/dashboard/general', [DashboardController::class, 'general']);
    Route::get('/dashboard/area/{area}', [DashboardController::class, 'byArea']);
    Route::get('/dashboard/consolidated', [DashboardController::class, 'consolidated']);
    Route::get('/dashboard/me', [DashboardController::class, 'myDashboard']);

    // Roles
    Route::get('/roles', [RoleController::class, 'index']);
    Route::patch('/roles/{role}/toggle-active', [RoleController::class, 'toggleActive']);

    // ── Configuration (superadmin) ──
    Route::get('/settings', [SystemSettingController::class, 'index']);
    Route::post('/settings', [SystemSettingController::class, 'store']);
    Route::put('/settings', [SystemSettingController::class, 'update']);
    Route::delete('/settings/{systemSetting}', [SystemSettingController::class, 'destroy']);

    // Message Templates (superadmin)
    Route::get('/message-templates', [MessageTemplateController::class, 'index']);
    Route::post('/message-templates', [MessageTemplateController::class, 'store']);
    Route::get('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'show']);
    Route::put('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
    Route::delete('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);

    // Automation triggers (superadmin)
    Route::post('/automation/detect-overdue', [AutomationController::class, 'triggerOverdueDetection']);
    Route::post('/automation/send-summary', [AutomationController::class, 'triggerDailySummary']);
    Route::post('/automation/send-reminders', [AutomationController::class, 'triggerDueReminders']);
    Route::post('/automation/detect-inactivity', [AutomationController::class, 'triggerInactivityDetection']);

    // Import (superadmin)
    Route::post('/import/tasks', [ImportController::class, 'importTasks']);

    // Attachments (Supabase S3)
    Route::post('/attachments', [AttachmentController::class, 'store']);
    Route::get('/tasks/{task}/attachments-v2', [AttachmentController::class, 'taskAttachments']);
    Route::get('/areas/{area}/attachments', [AttachmentController::class, 'areaAttachments']);
    Route::get('/attachments/{attachment}/signed-url', [AttachmentController::class, 'signedUrl']);
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
