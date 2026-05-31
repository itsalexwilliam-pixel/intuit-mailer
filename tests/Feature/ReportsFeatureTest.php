<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountId(): int
    {
        $planId = DB::table('plans')->insertGetId([
            'name' => 'Starter',
            'slug' => 'starter-' . uniqid(),
            'emails_per_day' => 5000,
            'campaigns_limit' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('accounts')->insertGetId([
            'name' => 'Acme Account',
            'plan_id' => $planId,
            'owner_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsAccountUser(?int $accountId = null): User
    {
        $accountId = $accountId ?? $this->createAccountId();

        $user = User::factory()->create([
            'account_id' => $accountId,
        ]);

        DB::table('accounts')
            ->where('id', $accountId)
            ->update(['owner_user_id' => $user->id]);

        DB::table('account_user')->insert([
            'account_id' => $accountId,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_reports_page_requires_authentication(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_reports_page_loads_for_authenticated_user_with_empty_state(): void
    {
        $this->actingAsAccountUser();

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Emails Sent');
        $response->assertSee('No sent emails found for the selected filters.');
    }

    public function test_reports_page_renders_metrics_with_seeded_data(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $campaignId = DB::table('campaigns')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Q2 Promo',
            'subject' => 'Promo',
            'body' => '<p>Hello</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueId = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'contact_id' => null,
            'email' => 'john@example.com',
            'subject' => 'Promo',
            'body' => '<p>Hello</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'q2-promo',
        ]);

        DB::table('email_opens')->insert([
            'email_queue_id' => $queueId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'opened_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('email_clicks')->insert([
            'email_queue_id' => $queueId,
            'url' => 'https://example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'clicked_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('unsubscribes')->insert([
            'contact_id' => null,
            'email' => 'john@example.com',
            'unsubscribed_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Emails Sent');
        $response->assertSee('Campaign Performance');
        $response->assertSee('Top UTM Sources');
        $response->assertSee('Top UTM Campaigns');
    }

    public function test_reports_filters_are_accepted(): void
    {
        $this->actingAsAccountUser();

        $response7d = $this->get(route('reports.index', ['date_range' => '7d']));
        $response7d->assertOk();

        $response30d = $this->get(route('reports.index', ['date_range' => '30d']));
        $response30d->assertOk();

        $responseCustom = $this->get(route('reports.index', [
            'date_range' => 'custom',
            'from' => now()->subDays(10)->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $responseCustom->assertOk();
    }

    public function test_single_email_report_page_shows_single_email_activity_log_rows(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $queueId = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => null,
            'contact_id' => null,
            'email' => 'activity@example.com',
            'type' => 'single',
            'subject' => 'Activity Subject',
            'body' => '<p>Activity Body</p>',
            'body_snapshot' => '<p>Activity Body Snapshot</p>',
            'from_email' => 'from@example.com',
            'from_name' => 'Report Sender',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('reports.single-email'));

        $response->assertOk();
        $response->assertSee('Single Email Activity Log');
        $response->assertSee('activity@example.com');
        $response->assertSee('Report Sender');
        $response->assertSee('Activity Subject');
        $response->assertSee(route('reports.email.show', ['id' => $queueId]));
    }

    public function test_single_email_report_excludes_campaign_rows(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $campaignId = DB::table('campaigns')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Campaign For Single Filter Test',
            'subject' => 'Campaign Subject',
            'body' => '<p>Campaign Body</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('email_queue')->insert([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'contact_id' => null,
            'email' => 'campaign@example.com',
            'type' => 'campaign',
            'subject' => 'Campaign Subject',
            'body' => '<p>Campaign Body</p>',
            'body_snapshot' => '<p>Campaign Body Snapshot</p>',
            'from_email' => 'from@example.com',
            'from_name' => 'Campaign Sender',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('reports.single-email'));

        $response->assertOk();
        $response->assertDontSee('campaign@example.com');
        $response->assertSee('No single email activity found for selected filters.');
    }

    public function test_reports_email_preview_returns_snapshot_for_same_account(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $queueId = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => null,
            'contact_id' => null,
            'email' => 'preview@example.com',
            'type' => 'single',
            'subject' => 'Preview Subject',
            'body' => '<p>Original Body</p>',
            'body_snapshot' => '<p>Snapshot Body</p>',
            'from_email' => 'from@example.com',
            'from_name' => 'Preview Sender',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('reports.email.show', ['id' => $queueId]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $queueId,
            'to' => 'preview@example.com',
            'from_name' => 'Preview Sender',
            'from_email' => 'from@example.com',
            'subject' => 'Preview Subject',
            'body_snapshot' => '<p>Snapshot Body</p>',
        ]);
    }

    public function test_reports_email_preview_falls_back_to_body_when_snapshot_missing(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $queueId = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => null,
            'contact_id' => null,
            'email' => 'fallback@example.com',
            'type' => 'single',
            'subject' => 'Fallback Subject',
            'body' => '<p>Fallback Body</p>',
            'body_snapshot' => null,
            'from_email' => 'from@example.com',
            'from_name' => 'Fallback Sender',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('reports.email.show', ['id' => $queueId]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $queueId,
            'body_snapshot' => '<p>Fallback Body</p>',
        ]);
    }

    public function test_reports_email_preview_is_scoped_to_account(): void
    {
        $accountA = $this->createAccountId();
        $accountB = $this->createAccountId();

        $userA = User::factory()->create(['account_id' => $accountA]);
        DB::table('accounts')->where('id', $accountA)->update(['owner_user_id' => $userA->id]);
        DB::table('account_user')->insert([
            'account_id' => $accountA,
            'user_id' => $userA->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueIdForB = DB::table('email_queue')->insertGetId([
            'account_id' => $accountB,
            'campaign_id' => null,
            'contact_id' => null,
            'email' => 'other-account@example.com',
            'type' => 'single',
            'subject' => 'Other Account',
            'body' => '<p>Other</p>',
            'body_snapshot' => '<p>Other Snapshot</p>',
            'from_email' => 'from@other.com',
            'from_name' => 'Other Sender',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson(route('reports.email.show', ['id' => $queueIdForB]));
        $response->assertNotFound();
    }

    public function test_utm_sections_show_aggregated_source_medium_and_campaign_counts(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $campaignId = DB::table('campaigns')->insertGetId([
            'account_id' => $accountId,
            'name' => 'UTM Aggregation Campaign',
            'subject' => 'UTM Subject',
            'body' => '<p>UTM</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $q1 = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'contact_id' => null,
            'email' => 'utm1@example.com',
            'type' => 'campaign',
            'subject' => 'UTM A',
            'body' => '<p>A</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-sale',
        ]);

        $q2 = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'contact_id' => null,
            'email' => 'utm2@example.com',
            'type' => 'campaign',
            'subject' => 'UTM B',
            'body' => '<p>B</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-sale',
        ]);

        DB::table('email_clicks')->insert([
            [
                'email_queue_id' => $q1,
                'url' => 'https://example.com/a',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'clicked_at' => now(),
                'created_at' => now(),
            ],
            [
                'email_queue_id' => $q2,
                'url' => 'https://example.com/b',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'clicked_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Top UTM Sources');
        $response->assertSee('newsletter');
        $response->assertSee('email');
        $response->assertSee('Top UTM Campaigns');
        $response->assertSee('spring-sale');
    }

    public function test_utm_data_respects_account_isolation(): void
    {
        $accountA = $this->createAccountId();
        $accountB = $this->createAccountId();

        $userA = User::factory()->create(['account_id' => $accountA]);
        DB::table('accounts')->where('id', $accountA)->update(['owner_user_id' => $userA->id]);
        DB::table('account_user')->insert([
            'account_id' => $accountA,
            'user_id' => $userA->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignA = DB::table('campaigns')->insertGetId([
            'account_id' => $accountA,
            'name' => 'A Campaign',
            'subject' => 'A',
            'body' => '<p>A</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignB = DB::table('campaigns')->insertGetId([
            'account_id' => $accountB,
            'name' => 'B Campaign',
            'subject' => 'B',
            'body' => '<p>B</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueA = DB::table('email_queue')->insertGetId([
            'account_id' => $accountA,
            'campaign_id' => $campaignA,
            'contact_id' => null,
            'email' => 'a@example.com',
            'type' => 'campaign',
            'subject' => 'A',
            'body' => '<p>A</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'visible-source',
            'utm_medium' => 'email',
            'utm_campaign' => 'visible-campaign',
        ]);

        $queueB = DB::table('email_queue')->insertGetId([
            'account_id' => $accountB,
            'campaign_id' => $campaignB,
            'contact_id' => null,
            'email' => 'b@example.com',
            'type' => 'campaign',
            'subject' => 'B',
            'body' => '<p>B</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'hidden-source',
            'utm_medium' => 'social',
            'utm_campaign' => 'hidden-campaign',
        ]);

        DB::table('email_clicks')->insert([
            [
                'email_queue_id' => $queueA,
                'url' => 'https://example.com/a',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'clicked_at' => now(),
                'created_at' => now(),
            ],
            [
                'email_queue_id' => $queueB,
                'url' => 'https://example.com/b',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'clicked_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $response = $this->actingAs($userA)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('visible-source');
        $response->assertSee('visible-campaign');
        $response->assertDontSee('hidden-source');
        $response->assertDontSee('hidden-campaign');
    }

    public function test_campaign_and_detailed_leads_exports_include_utm_columns_and_values(): void
    {
        $user = $this->actingAsAccountUser();
        $accountId = (int) $user->account_id;

        $campaignId = DB::table('campaigns')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Export UTM Campaign',
            'subject' => 'Export',
            'body' => '<p>Export</p>',
            'status' => 'draft',
            'scheduled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueId = DB::table('email_queue')->insertGetId([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'contact_id' => null,
            'email' => 'export@example.com',
            'type' => 'campaign',
            'subject' => 'Export Subject',
            'body' => '<p>Export Body</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'q3-launch',
            'utm_term' => 'segment-a',
            'utm_content' => 'cta-top',
        ]);

        DB::table('email_clicks')->insert([
            'email_queue_id' => $queueId,
            'url' => 'https://example.com/export',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'clicked_at' => now(),
            'created_at' => now(),
        ]);

        $campaignExport = $this->get(route('reports.export', ['type' => 'campaign']));
        $campaignExport->assertOk();
        $campaignContent = $campaignExport->streamedContent();

        $this->assertStringContainsString('UTM Source', $campaignContent);
        $this->assertStringContainsString('UTM Medium', $campaignContent);
        $this->assertStringContainsString('UTM Campaign', $campaignContent);
        $this->assertStringContainsString('newsletter', $campaignContent);
        $this->assertStringContainsString('email', $campaignContent);
        $this->assertStringContainsString('q3-launch', $campaignContent);

        $detailedExport = $this->get(route('reports.export', ['type' => 'detailed-leads']));
        $detailedExport->assertOk();
        $detailedContent = $detailedExport->streamedContent();

        $this->assertStringContainsString('UTM Source', $detailedContent);
        $this->assertStringContainsString('UTM Medium', $detailedContent);
        $this->assertStringContainsString('UTM Campaign', $detailedContent);
        $this->assertStringContainsString('UTM Term', $detailedContent);
        $this->assertStringContainsString('UTM Content', $detailedContent);
        $this->assertStringContainsString('newsletter', $detailedContent);
        $this->assertStringContainsString('email', $detailedContent);
        $this->assertStringContainsString('q3-launch', $detailedContent);
        $this->assertStringContainsString('segment-a', $detailedContent);
        $this->assertStringContainsString('cta-top', $detailedContent);
    }
}
