<?php

namespace Tests\Feature\Models\Concerns;

use App\Exceptions\InvalidStatusTransition;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Fixtures\FixtureDocument;
use Tests\Fixtures\FixtureStatus;
use Tests\TestCase;

class HasStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* A temporary table does not end the test's wrapping transaction in MySQL. */
        Schema::create('fixture_documents', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function test_allowed_status_changes_are_saved_and_recorded_with_user_and_comment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $document = FixtureDocument::query()->create(['status' => FixtureStatus::Draft]);

        $document->transitionTo(FixtureStatus::Issued, 'Sent to the customer');

        $this->assertSame(FixtureStatus::Issued, $document->fresh()->status);
        $history = $document->statusHistories()->sole();
        $this->assertSame('draft', $history->from_status);
        $this->assertSame('issued', $history->to_status);
        $this->assertSame('Sent to the customer', $history->comment);
        $this->assertSame($user->id, $history->user_id);
    }

    public function test_disallowed_status_changes_are_refused_and_nothing_is_recorded(): void
    {
        $document = FixtureDocument::query()->create(['status' => FixtureStatus::Draft]);

        try {
            $document->transitionTo(FixtureStatus::Void);
            $this->fail('Moving a draft straight to void should be refused.');
        } catch (InvalidStatusTransition $exception) {
            $this->assertSame('A document cannot change from "Draft" to "Void".', $exception->getMessage());
        }

        $this->assertSame(FixtureStatus::Draft, $document->fresh()->status);
        $this->assertSame(0, StatusHistory::query()->count());
    }

    public function test_history_entries_cannot_be_changed_or_deleted(): void
    {
        $document = FixtureDocument::query()->create(['status' => FixtureStatus::Draft]);
        $history = $document->recordStatusChange(null, FixtureStatus::Draft, 'Created');

        $this->expectException(LogicException::class);

        $history->update(['comment' => 'Rewritten']);
    }

    public function test_status_badge_and_history_components_display_the_changes(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Grace Namata']));
        $document = FixtureDocument::query()->create(['status' => FixtureStatus::Draft]);
        $document->transitionTo(FixtureStatus::Issued, 'Approved by phone');

        $badge = Blade::render('<x-status-badge :status="$status" />', ['status' => FixtureStatus::Issued]);
        $timeline = Blade::render('<x-status-history :histories="$histories" :labels="$labels" />', [
            'histories' => $document->statusHistories()->with('user')->get(),
            'labels' => ['draft' => 'Draft', 'issued' => 'Issued'],
        ]);

        $this->assertStringContainsString('text-primary-emphasis', $badge);
        $this->assertStringContainsString('Issued', $badge);
        $this->assertStringContainsString('Grace Namata', $timeline);
        $this->assertStringContainsString('Approved by phone', $timeline);
    }
}
