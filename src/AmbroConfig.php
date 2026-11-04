<?php

declare(strict_types=1);

namespace Beziworld\Ambro;

use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Enums\LabelFormat;
use Beziworld\Ambro\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable configuration of one Ambro Express account.
 *
 * The test environment (dev.api.ambro.opennet.pl) and the production
 * environment (api.ambro.opennet.pl) are separate; the documentation lists
 * plain HTTP addresses for both.
 */
final readonly class AmbroConfig
{
    public const string NAMESPACE = 'http://tempuri.org/';

    public const string PRODUCTION_ENDPOINT = 'http://api.ambro.opennet.pl/api.asmx';

    public const string SANDBOX_ENDPOINT = 'http://dev.api.ambro.opennet.pl/api.asmx';

    public function __construct(
        public string $username,
        #[\SensitiveParameter]
        public string $password,
        public bool $sandbox = false,
        public int $timeout = 30,
        public int $service = 38,
        public LabelFormat $labelFormat = LabelFormat::Pdf,
        public ?string $currency = null,
        public ?Location $sender = null,
        public int $readRetries = 2,
        public ?string $endpoint = null,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfiguration When the username or password is missing.
     */
    public static function fromArray(array $config): self
    {
        $string = static fn(string $key): ?string => is_scalar($config[$key] ?? null) && trim((string) $config[$key]) !== '' ? trim((string) $config[$key]) : null;
        $sender = is_array($config['sender'] ?? null) ? Location::fromArray($config['sender']) : null;
        $format = $string('label_format');

        return new self(
            username: $string('username') ?? throw InvalidConfiguration::missing('username', 'AMBRO_USERNAME'),
            password: $string('password') ?? throw InvalidConfiguration::missing('password', 'AMBRO_PASSWORD'),
            sandbox: filter_var($config['sandbox'] ?? false, FILTER_VALIDATE_BOOL),
            timeout: is_numeric($config['timeout'] ?? null) && (int) $config['timeout'] > 0 ? (int) $config['timeout'] : 30,
            service: is_numeric($config['service'] ?? null) ? (int) $config['service'] : 38,
            labelFormat: $format !== null ? (LabelFormat::tryFrom(strtoupper($format)) ?? LabelFormat::Pdf) : LabelFormat::Pdf,
            currency: ($currency = $string('currency')) !== null ? strtoupper($currency) : null,
            sender: $sender !== null && $sender->name !== '' ? $sender : null,
            readRetries: is_numeric($config['read_retries'] ?? null) ? max(0, (int) $config['read_retries']) : 2,
            endpoint: $string('endpoint'),
        );
    }

    public function endpoint(): string
    {
        return $this->endpoint ?? ($this->sandbox ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT);
    }
}
