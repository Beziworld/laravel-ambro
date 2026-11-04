<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Console;

use Beziworld\Ambro\AmbroManager;
use Beziworld\Ambro\Exceptions\AmbroException;
use Illuminate\Console\Command;

/**
 * Lists the services and additional services enabled for an account; a read-only connection check.
 */
final class ServicesCommand extends Command
{
    /** @var string */
    protected $signature = 'ambro:services {--connection= : Connection name from config/ambro.php}';

    /** @var string */
    protected $description = 'List the Ambro Express services enabled for the account';

    public function handle(AmbroManager $ambro): int
    {
        $connection = $this->option('connection');

        try {
            $services = $ambro->connection(is_string($connection) && $connection !== '' ? $connection : null)->servicesInfo();
        } catch (AmbroException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($services as $service) {
            $rows[] = [$service->id, $service->name, implode(', ', array_keys($service->additionalServices))];
        }

        $this->table(['ID', 'Service', 'Additional services'], $rows);

        return self::SUCCESS;
    }
}
