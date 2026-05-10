<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-row beforeSnapshot so the history view can diff a row against its own pre-edit state
 * instead of the neighbouring row's snapshot. Backfill walks existing rows in chronological
 * order and freezes each row's prior-state snapshot before any future row deletions can
 * shift the implicit predecessor relationship.
 */
final class Version20260510185639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highline_edit.before_snapshot + backfill from chronological predecessors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit ADD before_snapshot JSON DEFAULT NULL
        SQL);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, highline_id, snapshot, status, created_at FROM highline_edit ORDER BY highline_id, created_at'
        );

        $prevAppliedByHighline = [];
        foreach ($rows as $row) {
            $hid = (int) $row['highline_id'];
            $snapshot = is_array($row['snapshot']) ? $row['snapshot'] : json_decode((string) $row['snapshot'], true);
            $status = $row['status'];

            $before = $prevAppliedByHighline[$hid] ?? null;
            if ($before !== null) {
                $this->addSql(
                    'UPDATE highline_edit SET before_snapshot = :b WHERE id = :id',
                    ['b' => json_encode($before), 'id' => (int) $row['id']],
                );
            }

            // Only APPLIED rows mutate the canonical highline state — those become the
            // "before" for whatever comes next in chronological order.
            if ($status === 'applied') {
                $prevAppliedByHighline[$hid] = $snapshot;
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit DROP before_snapshot
        SQL);
    }
}
