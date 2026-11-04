<?php

declare(strict_types=1);

namespace Beziworld\Ambro;

use Beziworld\Ambro\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Resolves Ambro Express clients for any number of accounts.
 *
 * Named connections (each with its own credentials, sender and defaults)
 * are defined in config/ambro.php, in the same way as database
 * connections; options outside "connections" are shared defaults. Clients
 * for credentials stored at runtime (for example per tenant in the
 * database) are created with using(). Calls made directly on the manager go
 * to the default connection.
 *
 * @mixin AmbroClient
 */
final class AmbroManager
{
    /** @var array<string, AmbroClient> */
    private array $clients = [];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
    ) {}

    /**
     * Client of a configured connection; the default connection when no name is given.
     *
     * @throws InvalidConfiguration When the connection is not defined or incomplete.
     */
    public function connection(?string $name = null): AmbroClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= new AmbroClient($this->http, $this->configFor($name), $name);
    }

    /**
     * Client for credentials provided at runtime, merged with the shared options.
     *
     * @param AmbroConfig|array<string, mixed> $config Keys as in a connection: username, password, sandbox, sender, ...
     */
    public function using(AmbroConfig|array $config): AmbroClient
    {
        return new AmbroClient($this->http, $config instanceof AmbroConfig ? $config : AmbroConfig::fromArray(self::merge($this->shared(), $config)));
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config->get('ambro.default');

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
     * Names of the configured connections.
     *
     * @return list<string>
     */
    public function connectionNames(): array
    {
        $connections = $this->config->get('ambro.connections');

        if (! is_array($connections) || $connections === []) {
            return [$this->getDefaultConnection()];
        }

        return array_map(strval(...), array_keys($connections));
    }

    /**
     * Forgets resolved clients, for example after the configuration changed.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->connection(), $method];

        if (! is_callable($callable)) {
            throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', AmbroClient::class, $method));
        }

        return $callable(...$arguments);
    }

    private function configFor(string $name): AmbroConfig
    {
        $connections = $this->config->get('ambro.connections');

        if (! is_array($connections) || $connections === []) {
            if ($name !== $this->getDefaultConnection()) {
                throw InvalidConfiguration::unknownConnection($name);
            }

            return AmbroConfig::fromArray($this->shared());
        }

        $connection = $connections[$name] ?? null;

        if (! is_array($connection)) {
            throw InvalidConfiguration::unknownConnection($name);
        }

        return AmbroConfig::fromArray(self::merge($this->shared(), $connection));
    }

    /**
     * Applies connection options over the shared ones; empty values (unset environment variables) do not override.
     *
     * @param array<string, mixed> $shared
     * @param array<array-key, mixed> $connection
     * @return array<string, mixed>
     */
    private static function merge(array $shared, array $connection): array
    {
        foreach ($connection as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $key = (string) $key;
            $shared[$key] = is_array($value) && is_array($shared[$key] ?? null) ? self::merge($shared[$key], $value) : $value;
        }

        return $shared;
    }

    /**
     * Options shared by every connection: everything outside "default" and "connections".
     *
     * @return array<string, mixed>
     */
    private function shared(): array
    {
        $config = $this->config->get('ambro', []);
        $config = is_array($config) ? $config : [];
        unset($config['default'], $config['connections']);

        /** @var array<string, mixed> $config */
        return $config;
    }
}
