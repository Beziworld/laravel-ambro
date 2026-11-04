<?php

declare(strict_types=1);

use Beziworld\Ambro\AmbroClient;
use Beziworld\Ambro\AmbroConfig;
use Beziworld\Ambro\AmbroManager;
use Beziworld\Ambro\Exceptions\InvalidConfiguration;
use Beziworld\Ambro\Facades\Ambro;
use Beziworld\Ambro\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('resolves the default connection from the container', function (): void {
    $client = app(AmbroClient::class);

    expect($client->connectionName())->toBe('default')
        ->and($client->config()->endpoint())->toBe(TestCase::SANDBOX)
        ->and(app(AmbroManager::class)->connection())->toBe(app(AmbroManager::class)->connection());
});

it('uses production when the sandbox is disabled', function (): void {
    config()->set('ambro.sandbox', false);
    Ambro::purge();

    expect(Ambro::config()->endpoint())->toBe(TestCase::PRODUCTION);
});

it('supports named connections that override shared options', function (): void {
    config()->set('ambro.connections', [
        'default' => [],
        'warehouse' => ['username' => 'wh', 'password' => 'secret', 'sender' => ['city' => 'Poznań'], 'service' => 200],
    ]);

    TestCase::fakeAmbro(['GetServices' => TestCase::asmx('GetServices', '<Services />')]);

    $client = Ambro::connection('warehouse');
    $client->services();

    expect(Ambro::connectionNames())->toBe(['default', 'warehouse'])
        ->and($client->config()->service)->toBe(200)
        ->and($client->config()->sender?->city)->toBe('Poznań')
        ->and($client->config()->sender?->name)->toBe('ACME Sp. z o.o.');

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:token/t:UserName') === 'wh'
        && TestCase::at($request, '//t:token/t:Password') === 'secret');
});

it('creates clients for runtime credentials', function (): void {
    TestCase::fakeAmbro(['GetServices' => TestCase::asmx('GetServices', '<Services />')]);

    $client = Ambro::using(['username' => 'tenant-7', 'password' => 'p7']);
    $client->services();

    expect($client->connectionName())->toBeNull()
        ->and($client->config()->sandbox)->toBeTrue();

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:token/t:UserName') === 'tenant-7');
});

it('accepts a prepared config object', function (): void {
    $client = Ambro::using(new AmbroConfig('u', 'p', endpoint: 'https://proxy.example.com/api.asmx'));

    expect($client->config()->endpoint())->toBe('https://proxy.example.com/api.asmx');
});

it('throws for an unknown connection', function (): void {
    Ambro::connection('missing');
})->throws(InvalidConfiguration::class, 'connection "missing"');

it('throws when the credentials are missing', function (): void {
    config()->set('ambro.username', null);
    Ambro::purge();

    Ambro::connection();
})->throws(InvalidConfiguration::class, 'AMBRO_USERNAME');

it('reads defaults from the configuration', function (): void {
    $config = AmbroConfig::fromArray(['username' => 'u', 'password' => 'p', 'label_format' => 'pdfa4', 'currency' => 'eur', 'timeout' => 'x', 'read_retries' => -3]);

    expect($config->labelFormat->value)->toBe('PDFA4')
        ->and($config->currency)->toBe('EUR')
        ->and($config->timeout)->toBe(30)
        ->and($config->readRetries)->toBe(0)
        ->and($config->sender)->toBeNull()
        ->and($config->service)->toBe(38);
});

it('rejects unknown methods on the manager', function (): void {
    app(AmbroManager::class)->doesNotExist();
})->throws(BadMethodCallException::class);

it('runs the services command', function (): void {
    TestCase::fakeAmbro(['GetServicesInfo' => TestCase::asmx('GetServicesInfo', '<Services><ServiceInfo><ID>38</ID><Name>Standard</Name><AdditionalServicesInfo><AdditionalServiceInfo><Code>SMS</Code><Description>SMS</Description></AdditionalServiceInfo></AdditionalServicesInfo></ServiceInfo></Services>')]);

    $this->artisan('ambro:services')
        ->expectsTable(['ID', 'Service', 'Additional services'], [[38, 'Standard', 'SMS']])
        ->assertSuccessful();
});

it('reports command errors', function (): void {
    TestCase::fakeAmbro(['GetServicesInfo' => TestCase::asmx('GetServicesInfo', '', 401, 'Authentication failed')]);

    $this->artisan('ambro:services', ['--connection' => 'default'])
        ->expectsOutputToContain('rejected the credentials')
        ->assertFailed();
});
