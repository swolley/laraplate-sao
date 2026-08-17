<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestStatus;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;

uses(RefreshDatabase::class);

/**
 * @param  list<Capability>  $capabilities
 */
function sao_logs_connection(string $driverKey, array $capabilities, string $secret = 'shared'): Connection
{
    return Connection::factory()->create([
        'driver_key' => $driverKey,
        'capabilities' => $capabilities,
        'credential' => ['secret' => $secret],
        'base_url' => null,
    ]);
}

function sao_bind_logs(Connection $connection, Project $project): ProjectBinding
{
    return ProjectBinding::factory()->create([
        'project_id' => $project->getKey(),
        'connection_id' => $connection->getKey(),
        'capability' => Capability::Logs,
        'remote_identifier' => 'acme',
    ]);
}

/**
 * @return array<string, mixed>
 */
function sao_glitchtip_body(string $id = 'i-1', string $title = 'TypeError x'): array
{
    return ['data' => ['issue' => ['id' => $id, 'title' => $title, 'level' => 'error']]];
}

function sao_webhook_url(Connection $connection): string
{
    return '/api/v1/webhooks/' . $connection->getKey();
}

test('a signed logs delivery ingests a native-keyed signal into the bound project', function (): void {
    $project = Project::factory()->create();
    $connection = sao_logs_connection('glitchtip', [Capability::Logs]);
    sao_bind_logs($connection, $project);

    $response = $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), [
        'X-GlitchTip-Token' => 'shared',
        'X-Delivery-Id' => 'del-1',
    ]);

    $response->assertStatus(202)->assertJson(['result' => 'ingested']);

    $signal = Signal::query()->where('project_id', $project->getKey())->sole();

    expect($signal->group_key)->toBe('glitchtip:i-1')
        ->and($signal->occurrence_count)->toBe(1)
        ->and(IngestEvent::query()->where('status', IngestStatus::Ingested)->count())->toBe(1);
});

test('a delivery with a wrong token is rejected and nothing is stored', function (): void {
    $project = Project::factory()->create();
    $connection = sao_logs_connection('glitchtip', [Capability::Logs]);
    sao_bind_logs($connection, $project);

    $response = $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), [
        'X-GlitchTip-Token' => 'wrong',
    ]);

    $response->assertStatus(401);

    expect(Signal::query()->count())->toBe(0)
        ->and(IngestEvent::query()->count())->toBe(0);
});

test('a re-delivery with the same delivery id is deduped, not re-ingested', function (): void {
    $project = Project::factory()->create();
    $connection = sao_logs_connection('glitchtip', [Capability::Logs]);
    sao_bind_logs($connection, $project);

    $headers = ['X-GlitchTip-Token' => 'shared', 'X-Delivery-Id' => 'del-1'];

    $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), $headers)->assertStatus(202);
    $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), $headers)->assertStatus(202);

    $signal = Signal::query()->where('project_id', $project->getKey())->sole();

    expect($signal->occurrence_count)->toBe(1)
        ->and(SignalOccurrence::query()->count())->toBe(1)
        ->and(IngestEvent::query()->count())->toBe(1);
});

test('a delivery to a non-logs connection is unsupported', function (): void {
    $connection = sao_logs_connection('github', [Capability::Issues]);

    $response = $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), [
        'X-GlitchTip-Token' => 'shared',
    ]);

    $response->assertStatus(422)->assertJson(['result' => 'driver-has-no-logs-capability']);
});

test('an authentic delivery with no logs binding is accepted and audited but produces no signal', function (): void {
    $connection = sao_logs_connection('glitchtip', [Capability::Logs]);

    $response = $this->postJson(sao_webhook_url($connection), sao_glitchtip_body(), [
        'X-GlitchTip-Token' => 'shared',
        'X-Delivery-Id' => 'del-1',
    ]);

    $response->assertStatus(202)->assertJson(['result' => 'no-logs-binding']);

    expect(Signal::query()->count())->toBe(0)
        ->and(IngestEvent::query()->where('status', IngestStatus::Discarded)->where('outcome', 'no-logs-binding')->count())->toBe(1);
});
