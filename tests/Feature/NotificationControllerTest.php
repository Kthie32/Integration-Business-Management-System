<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_shows_unread_count_and_recent_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new SystemMessage('Requisition awaiting approval', 'REQ-2026-00004 needs your decision.', '/dashboard', 'clipboard-check'));
        $user->notify(new SystemMessage('Low stock', 'Printer paper is below its minimum level.'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications (2 unread)')
            ->assertSee('Requisition awaiting approval')
            ->assertSee('Printer paper is below its minimum level.');
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $user = User::factory()->create();
        $user->notify(new SystemMessage('Payslip ready', 'Your November payslip is available.', route('account.profile')));
        $notification = $user->notifications()->sole();

        $this->actingAs($user)->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('account.profile'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_links_to_other_websites_are_never_followed(): void
    {
        $user = User::factory()->create();
        $user->notify(new SystemMessage('Suspicious', 'Click here', 'https://evil.example/phish'));
        $user->notify(new SystemMessage('Suspicious', 'Click here', '//evil.example/phish'));
        $user->notify(new SystemMessage('Suspicious', 'Click here', '/\\evil.example/phish'));

        foreach ($user->notifications as $notification) {
            $this->actingAs($user)->post(route('notifications.open', $notification->id))
                ->assertRedirect(route('notifications.index'));
        }
    }

    public function test_users_cannot_open_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new SystemMessage('Private', 'Only for the owner.'));
        $notification = $owner->notifications()->sole();

        $this->actingAs(User::factory()->create())->post(route('notifications.open', $notification->id))->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new SystemMessage('One', 'First'));
        $user->notify(new SystemMessage('Two', 'Second'));
        $other->notify(new SystemMessage('Three', 'Third'));

        $this->actingAs($user)->post(route('notifications.read-all'))->assertSessionHas('success');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notifications_page_lists_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new SystemMessage('Invoice overdue', 'INV-2026-00010 is 5 days overdue.'));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Invoice overdue');
    }
}
