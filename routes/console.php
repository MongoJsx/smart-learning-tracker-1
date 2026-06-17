<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Notification\NotificationEmailSender;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:send-due', function () {
    if (! Schema::hasTable((new User())->getTable())) {
        $this->comment('User table missing.');
        return 0;
    }

    $sender = app(NotificationEmailSender::class);
    $users = User::query()->get();
    $sent = 0;

    foreach ($users as $user) {
        $sent += $sender->sendDueForUser($user);
    }

    $this->comment("Sent {$sent} notification(s).");
    return 0;
})->purpose('Send due email notifications');

Schedule::command('notifications:send-due')->everyMinute();
