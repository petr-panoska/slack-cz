<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename the "highline" domain to "line": the entity represents a line of any type
 * (highline/midline/longline/waterline), so the table family is renamed highline* → line*
 * and the highline_id FK columns → line_id. Tables and columns are RENAMEd (data preserved);
 * the Doctrine-hashed FK / FK-index names are normalised by the follow-up diff migration.
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename highline* tables/columns to line* (data-preserving)';
    }

    public function up(Schema $schema): void
    {
        // Tables
        $this->addSql('ALTER TABLE highline RENAME TO line');
        $this->addSql('ALTER TABLE highline_crossing RENAME TO line_crossing');
        $this->addSql('ALTER TABLE highline_edit RENAME TO line_edit');
        $this->addSql('ALTER TABLE highline_photo RENAME TO line_photo');
        $this->addSql('ALTER TABLE highline_photo_comment RENAME TO line_photo_comment');
        $this->addSql('ALTER TABLE highline_photo_like RENAME TO line_photo_like');

        // FK columns
        $this->addSql('ALTER TABLE line_crossing RENAME COLUMN highline_id TO line_id');
        $this->addSql('ALTER TABLE line_edit RENAME COLUMN highline_id TO line_id');
        $this->addSql('ALTER TABLE line_photo RENAME COLUMN highline_id TO line_id');

        // Primary keys
        $this->addSql('ALTER INDEX highline_pkey RENAME TO line_pkey');
        $this->addSql('ALTER INDEX highline_crossing_pkey RENAME TO line_crossing_pkey');
        $this->addSql('ALTER INDEX highline_edit_pkey RENAME TO line_edit_pkey');
        $this->addSql('ALTER INDEX highline_photo_pkey RENAME TO line_photo_pkey');
        $this->addSql('ALTER INDEX highline_photo_comment_pkey RENAME TO line_photo_comment_pkey');
        $this->addSql('ALTER INDEX highline_photo_like_pkey RENAME TO line_photo_like_pkey');

        // Explicitly-named indexes / unique constraints (must match the entity attributes)
        $this->addSql('ALTER INDEX idx_highline_edit_status RENAME TO idx_line_edit_status');
        $this->addSql('ALTER INDEX idx_highline_photo_highline_created RENAME TO idx_line_photo_line_created');
        $this->addSql('ALTER INDEX idx_highline_photo_comment_photo_created RENAME TO idx_line_photo_comment_photo_created');
        $this->addSql('ALTER INDEX uniq_highline_photo_like_photo_user RENAME TO uniq_line_photo_like_photo_user');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_line_photo_like_photo_user RENAME TO uniq_highline_photo_like_photo_user');
        $this->addSql('ALTER INDEX idx_line_photo_comment_photo_created RENAME TO idx_highline_photo_comment_photo_created');
        $this->addSql('ALTER INDEX idx_line_photo_line_created RENAME TO idx_highline_photo_highline_created');
        $this->addSql('ALTER INDEX idx_line_edit_status RENAME TO idx_highline_edit_status');

        $this->addSql('ALTER INDEX line_photo_like_pkey RENAME TO highline_photo_like_pkey');
        $this->addSql('ALTER INDEX line_photo_comment_pkey RENAME TO highline_photo_comment_pkey');
        $this->addSql('ALTER INDEX line_photo_pkey RENAME TO highline_photo_pkey');
        $this->addSql('ALTER INDEX line_edit_pkey RENAME TO highline_edit_pkey');
        $this->addSql('ALTER INDEX line_crossing_pkey RENAME TO highline_crossing_pkey');
        $this->addSql('ALTER INDEX line_pkey RENAME TO highline_pkey');

        $this->addSql('ALTER TABLE line_photo RENAME COLUMN line_id TO highline_id');
        $this->addSql('ALTER TABLE line_edit RENAME COLUMN line_id TO highline_id');
        $this->addSql('ALTER TABLE line_crossing RENAME COLUMN line_id TO highline_id');

        $this->addSql('ALTER TABLE line_photo_like RENAME TO highline_photo_like');
        $this->addSql('ALTER TABLE line_photo_comment RENAME TO highline_photo_comment');
        $this->addSql('ALTER TABLE line_photo RENAME TO highline_photo');
        $this->addSql('ALTER TABLE line_edit RENAME TO highline_edit');
        $this->addSql('ALTER TABLE line_crossing RENAME TO highline_crossing');
        $this->addSql('ALTER TABLE line RENAME TO highline');
    }
}
