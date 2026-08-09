<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class NamedLock
{
    public static function acquire(PDO $connection, string $name, int $timeoutSeconds = 10): void
    {
        if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $timeout = $connection->prepare('SET statement_timeout TO ' . ($timeoutSeconds * 1000));
            $timeout->execute();
            try {
                $statement = $connection->prepare('SELECT pg_advisory_lock(hashtext(:name))');
                $statement->execute(['name' => $name]);
            } finally {
                $reset = $connection->prepare('SET statement_timeout TO 0');
                $reset->execute();
            }
            return;
        }

        $statement = $connection->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->bindValue(':name', $name);
        $statement->bindValue(':timeout', $timeoutSeconds, PDO::PARAM_INT);
        $statement->execute();
        if ((int) $statement->fetchColumn() !== 1) {
            throw new \RuntimeException("No fue posible adquirir el bloqueo {$name}.");
        }
    }

    public static function release(PDO $connection, string $name): void
    {
        $sql = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? 'SELECT pg_advisory_unlock(hashtext(:name))'
            : 'SELECT RELEASE_LOCK(:name)';
        $statement = $connection->prepare($sql);
        $statement->execute(['name' => $name]);
    }
}
