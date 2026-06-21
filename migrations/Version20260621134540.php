<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follow-up to Version20260621120000: normalise the Doctrine-hashed FK / unique index
 * names on the renamed line* tables (the hash is derived from table+column names, which
 * changed in the rename). Pure index renames — no data touched.
 *
 * NB: deliberately excludes the unrelated pre-existing drift the auto-diff also picked up
 * (orphan *_id_seq sequences from the original SERIAL columns, and a messenger_messages
 * index consolidation) — neither is part of the highline→line rename.
 */
final class Version20260621134540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise FK/unique index names on renamed line* tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_34e105d55e237e06 RENAME TO UNIQ_D114B4F65E237E06');
        $this->addSql('ALTER INDEX uniq_34e105d5989d9b62 RENAME TO UNIQ_D114B4F6989D9B62');
        $this->addSql('ALTER INDEX idx_34e105d5a69b8ad7 RENAME TO IDX_D114B4F6A69B8AD7');
        $this->addSql('ALTER INDEX idx_34e105d5b03a8386 RENAME TO IDX_D114B4F6B03A8386');
        $this->addSql('ALTER INDEX idx_13c974c647791474 RENAME TO IDX_C22683D64D7B7542');
        $this->addSql('ALTER INDEX idx_13c974c6a76ed395 RENAME TO IDX_C22683D6A76ED395');
        $this->addSql('ALTER INDEX idx_b9d1e77a47791474 RENAME TO IDX_A782DC34D7B7542');
        $this->addSql('ALTER INDEX idx_b9d1e77adab5a938 RENAME TO IDX_A782DC3DAB5A938');
        $this->addSql('ALTER INDEX idx_b9d1e77afc6b21f1 RENAME TO IDX_A782DC3FC6B21F1');
        $this->addSql('ALTER INDEX idx_593d017947791474 RENAME TO IDX_EB33A39B4D7B7542');
        $this->addSql('ALTER INDEX idx_593d0179a2b28fe8 RENAME TO IDX_EB33A39BA2B28FE8');
        $this->addSql('ALTER INDEX idx_78b38bd77e9e4c8c RENAME TO IDX_397F51887E9E4C8C');
        $this->addSql('ALTER INDEX idx_78b38bd7f675f31b RENAME TO IDX_397F5188F675F31B');
        $this->addSql('ALTER INDEX idx_faf245ad7e9e4c8c RENAME TO IDX_93E9E1AC7E9E4C8C');
        $this->addSql('ALTER INDEX idx_faf245ada76ed395 RENAME TO IDX_93E9E1ACA76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_93e9e1aca76ed395 RENAME TO idx_faf245ada76ed395');
        $this->addSql('ALTER INDEX idx_93e9e1ac7e9e4c8c RENAME TO idx_faf245ad7e9e4c8c');
        $this->addSql('ALTER INDEX idx_397f5188f675f31b RENAME TO idx_78b38bd7f675f31b');
        $this->addSql('ALTER INDEX idx_397f51887e9e4c8c RENAME TO idx_78b38bd77e9e4c8c');
        $this->addSql('ALTER INDEX idx_eb33a39ba2b28fe8 RENAME TO idx_593d0179a2b28fe8');
        $this->addSql('ALTER INDEX idx_eb33a39b4d7b7542 RENAME TO idx_593d017947791474');
        $this->addSql('ALTER INDEX idx_a782dc3fc6b21f1 RENAME TO idx_b9d1e77afc6b21f1');
        $this->addSql('ALTER INDEX idx_a782dc3dab5a938 RENAME TO idx_b9d1e77adab5a938');
        $this->addSql('ALTER INDEX idx_a782dc34d7b7542 RENAME TO idx_b9d1e77a47791474');
        $this->addSql('ALTER INDEX idx_c22683d6a76ed395 RENAME TO idx_13c974c6a76ed395');
        $this->addSql('ALTER INDEX idx_c22683d64d7b7542 RENAME TO idx_13c974c647791474');
        $this->addSql('ALTER INDEX idx_d114b4f6b03a8386 RENAME TO idx_34e105d5b03a8386');
        $this->addSql('ALTER INDEX idx_d114b4f6a69b8ad7 RENAME TO idx_34e105d5a69b8ad7');
        $this->addSql('ALTER INDEX uniq_d114b4f6989d9b62 RENAME TO uniq_34e105d5989d9b62');
        $this->addSql('ALTER INDEX uniq_d114b4f65e237e06 RENAME TO uniq_34e105d55e237e06');
    }
}
