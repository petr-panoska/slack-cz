<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510113004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Highline.isVerified + createdBy, create highline_edit table; flag legacy 254 highlines as verified';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE highline_edit (id SERIAL NOT NULL, highline_id INT NOT NULL, proposed_by_id INT DEFAULT NULL, reviewed_by_id INT DEFAULT NULL, snapshot JSON NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B9D1E77A47791474 ON highline_edit (highline_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B9D1E77ADAB5A938 ON highline_edit (proposed_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B9D1E77AFC6B21F1 ON highline_edit (reviewed_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_highline_edit_status ON highline_edit (status)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_edit.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_edit.reviewed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit ADD CONSTRAINT FK_B9D1E77A47791474 FOREIGN KEY (highline_id) REFERENCES highline (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit ADD CONSTRAINT FK_B9D1E77ADAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit ADD CONSTRAINT FK_B9D1E77AFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD created_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD is_verified BOOLEAN DEFAULT false NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD CONSTRAINT FK_34E105D5B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_34E105D5B03A8386 ON highline (created_by_id)
        SQL);

        // Seed: every existing highline came from the legacy import — flag them as verified
        // so the proposal-flow gate engages from day one.
        $this->addSql(<<<'SQL'
            UPDATE highline SET is_verified = TRUE WHERE legacy_id IS NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit DROP CONSTRAINT FK_B9D1E77A47791474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit DROP CONSTRAINT FK_B9D1E77ADAB5A938
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_edit DROP CONSTRAINT FK_B9D1E77AFC6B21F1
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline_edit
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP CONSTRAINT FK_34E105D5B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_34E105D5B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP created_by_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP is_verified
        SQL);
    }
}
