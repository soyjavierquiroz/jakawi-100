<?php

namespace Tests\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use LogicException;

// Production evidence remains permanent. Only the isolated test database can reset
// its observation boundary along with the fixtures in committed outbox/race suites.
trait EvidenceDatabaseTruncation
{
    use DatabaseTruncation {
        truncateTablesForConnection as private truncateFixtureTables;
    }

    protected function truncateTablesForConnection(ConnectionInterface $connection, ?string $name): void
    {
        if (! app()->environment('testing') || $connection->getConfig('host') !== 'db-test'
            || $connection->getDatabaseName() !== 'jakawi_test') {
            throw new LogicException('REFUSING DESTRUCTIVE TEST: database is not isolated.');
        }
        if (! $connection->getSchemaBuilder()->hasTable('membership_evidence_journal')) {
            $this->truncateFixtureTables($connection, $name);

            return;
        }
        $tables = ['users', 'memberships', 'membership_bank_payments', 'membership_purchases', 'identity_links'];
        $connection->transaction(function () use ($connection, $name, $tables): void {
            $connection->statement('ALTER TABLE membership_evidence_journal DISABLE TRIGGER membership_evidence_permanent');
            $connection->statement('ALTER TABLE membership_evidence_boundary DISABLE TRIGGER membership_evidence_boundary_permanent');
            foreach ($tables as $table) {
                $connection->statement("ALTER TABLE {$table} DISABLE TRIGGER {$table}_membership_evidence");
                $connection->statement("ALTER TABLE {$table} DISABLE TRIGGER {$table}_evidence_no_truncate");
            }
            $connection->statement('ALTER TABLE membership_bank_imports DISABLE TRIGGER preserve_bank_imports');
            $connection->statement('ALTER TABLE bank_statement_entries DISABLE TRIGGER preserve_bank_entries');
            $this->truncateFixtureTables($connection, $name);
            $connection->statement('ALTER TABLE membership_bank_imports ENABLE TRIGGER preserve_bank_imports');
            $connection->statement('ALTER TABLE bank_statement_entries ENABLE TRIGGER preserve_bank_entries');
            $connection->statement('INSERT INTO membership_evidence_boundary VALUES (1, clock_timestamp()) ON CONFLICT DO NOTHING');
            foreach ($tables as $table) {
                $connection->statement("ALTER TABLE {$table} ENABLE TRIGGER {$table}_membership_evidence");
                $connection->statement("ALTER TABLE {$table} ENABLE TRIGGER {$table}_evidence_no_truncate");
            }
            $connection->statement('ALTER TABLE membership_evidence_journal ENABLE TRIGGER membership_evidence_permanent');
            $connection->statement('ALTER TABLE membership_evidence_boundary ENABLE TRIGGER membership_evidence_boundary_permanent');
        });
    }
}
