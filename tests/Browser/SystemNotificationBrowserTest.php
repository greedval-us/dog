<?php

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

test('reading one or all notifications removes them from the bell and preserves the records', function (string $locale) {
    $owner = User::factory()->create(['locale' => $locale]);
    $owner->notify(SystemNotification::siteUpdate(
        ['en' => 'Read earlier', 'ru' => 'Прочитано ранее'],
        ['en' => 'An earlier update', 'ru' => 'Предыдущее обновление'],
    ));
    $read = $owner->notifications()->sole();
    $read->markAsRead();
    $this->travel(1)->seconds();
    $owner->notify(SystemNotification::siteUpdate(
        ['en' => 'New update', 'ru' => 'Новое обновление'],
        ['en' => 'The site has been updated', 'ru' => 'Сайт обновлён'],
    ));
    $this->travel(1)->seconds();
    for ($index = 0; $index < 20; $index++) {
        $owner->notify(SystemNotification::passwordChanged());
    }
    $this->travel(1)->seconds();
    $owner->notify(SystemNotification::siteUpdate(
        ['en' => 'Latest update', 'ru' => 'Последнее обновление'],
        ['en' => 'The latest site update', 'ru' => 'Последние изменения сайта'],
    ));
    $selected = $owner->notifications()->first();
    $this->actingAs($owner->refresh());
    $isRussian = $locale === 'ru';
    $title = $isRussian ? 'Последнее обновление' : 'Latest update';
    $oldTitle = $isRussian ? 'Прочитано ранее' : 'Read earlier';
    $empty = $isRussian ? 'Нет непрочитанных уведомлений' : 'No unread notifications';
    $readAll = $isRussian ? 'Прочитать все' : 'Mark all as read';
    $close = $isRussian ? 'Закрыть уведомления' : 'Close notifications';
    $readOne = $isRussian ? "Отметить как прочитанное: {$title}" : "Mark as read: {$title}";
    $bellWith21 = $isRussian ? 'Уведомления, непрочитанных: 21' : 'Notifications, 21 unread';
    $bell = $isRussian ? 'Уведомления' : 'Notifications';

    $page = visit(route('home', absolute: false));
    $page->resize($isRussian ? 390 : 1440, 900)
        ->click('.notification-bell')
        ->assertSeeIn('.notification-panel', $title)
        ->assertDontSeeIn('.notification-panel', $oldTitle)
        ->assertPresent('.notification-footer')
        ->click("button[aria-label=\"{$readOne}\"]")
        ->assertDontSeeIn('.notification-panel', $title)
        ->assertAttribute('.notification-bell', 'aria-label', $bellWith21);

    expect($selected->refresh()->read_at)->not->toBeNull();
    $page->press($readAll)
        ->assertSeeIn('.notification-empty', $empty)
        ->assertMissing('.notification-item')
        ->assertMissing('.notification-footer')
        ->assertMissing('.notification-count')
        ->assertAttribute('.notification-bell', 'aria-label', $bell)
        ->click("button[aria-label=\"{$close}\"]")
        ->assertMissing('.notification-panel')
        ->click('.notification-bell')
        ->assertSeeIn('.notification-empty', $empty)
        ->assertMissing('.notification-item')
        ->assertNoJavaScriptErrors();

    expect($owner->unreadNotifications()->count())->toBe(0);
    expect($owner->notifications()->count())->toBe(23);
})->with(['en', 'ru']);

test('older unread notifications remain accessible after reading every visible notification', function () {
    $owner = User::factory()->create(['locale' => 'en']);
    for ($index = 0; $index < 21; $index++) {
        $owner->notify(SystemNotification::siteUpdate(
            ['en' => 'Update '.$index, 'ru' => 'Обновление '.$index],
            ['en' => 'The site has been updated', 'ru' => 'Сайт обновлён'],
        ));
        $this->travel(1)->seconds();
    }
    $this->actingAs($owner->refresh());

    $page = visit(route('home', absolute: false));
    $page->click('.notification-bell')
        ->assertSeeIn('.notification-panel', 'Update 20');
    for ($index = 20; $index > 0; $index--) {
        $page->click('.notification-item:first-child .notification-read-button')
            ->assertMissing('button[aria-label="Mark as read: Update '.$index.'"]');
    }
    $page->assertMissing('.notification-item')
        ->assertMissing('.notification-empty')
        ->assertPresent('.notification-footer')
        ->assertAttribute('.notification-bell', 'aria-label', 'Notifications, 1 unread')
        ->press('Older notifications')
        ->assertSeeIn('.notification-list', 'Update 0')
        ->assertMissing('.notification-footer')
        ->assertNoJavaScriptErrors();

    expect($owner->unreadNotifications()->count())->toBe(1);
    expect($owner->readNotifications()->count())->toBe(20);
    expect($owner->notifications()->count())->toBe(21);
});
