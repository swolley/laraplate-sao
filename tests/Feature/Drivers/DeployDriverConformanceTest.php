<?php

declare(strict_types=1);

use Modules\SAO\Drivers\External\GitHubDeploymentDriver;
use Modules\SAO\Drivers\External\WebhookDeployDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Tests\Support\Conformance\DeployConformance;

function deployContext(string $secret = 'shared'): BindingContext
{
    return new BindingContext(new ConnectionContext(baseUrl: null, credentials: ['secret' => $secret]));
}

test('the generic webhook deploy driver passes the deploy conformance battery', function (): void {
    $payload = (string) json_encode([
        'version' => '1.4.0',
        'status' => 'succeeded',
        'environment' => 'production',
        'external_id' => 'dep-1',
    ]);

    DeployConformance::assert(new WebhookDeployDriver, deployContext(), $payload, ['X-Deploy-Token' => 'shared']);
});

test('the generic webhook deploy driver unpacks a batch and defaults an unknown status to succeeded', function (): void {
    $payload = (string) json_encode([
        'deployments' => [
            ['version' => '1.0.0', 'status' => 'started', 'environment' => 'staging'],
            ['version' => '1.0.0', 'environment' => 'production'],
        ],
    ]);

    $events = (new WebhookDeployDriver)->unpack(deployContext(), $payload);

    expect($events)->toHaveCount(2)
        ->and($events[0]->status)->toBe(DeploymentStatus::Started)
        ->and($events[1]->status)->toBe(DeploymentStatus::Succeeded);
});

test('the github deployment driver passes the deploy conformance battery', function (): void {
    $payload = githubDeploymentPayload('success', 'v2.0.0', 'production');
    $headers = ['X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $payload, 'shared')];

    DeployConformance::assert(new GitHubDeploymentDriver, deployContext(), $payload, $headers);
});

test('the github deployment driver maps states to deployment statuses', function (): void {
    $driver = new GitHubDeploymentDriver;

    $success = $driver->unpack(deployContext(), githubDeploymentPayload('success', 'v2.0.0', 'production'));
    $failure = $driver->unpack(deployContext(), githubDeploymentPayload('failure'));
    $pending = $driver->unpack(deployContext(), githubDeploymentPayload('in_progress'));

    expect($success[0]->status)->toBe(DeploymentStatus::Succeeded)
        ->and($success[0]->version)->toBe('v2.0.0')
        ->and($success[0]->environmentName)->toBe('production')
        ->and($success[0]->externalId)->toBe('42')
        ->and($failure[0]->status)->toBe(DeploymentStatus::Failed)
        ->and($pending[0]->status)->toBe(DeploymentStatus::Started);
});

function githubDeploymentPayload(string $state, string $ref = 'v2.0.0', string $environment = 'production'): string
{
    return (string) json_encode([
        'action' => 'created',
        'deployment_status' => [
            'state' => $state,
            'environment' => $environment,
        ],
        'deployment' => [
            'id' => 42,
            'sha' => 'abc123',
            'ref' => $ref,
            'environment' => $environment,
        ],
    ]);
}
