<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order_audit_log table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE order_audit_log (
                id UUID NOT NULL,
                order_id UUID NOT NULL,
                partner_id VARCHAR(64) NOT NULL,
                order_id_value VARCHAR(64) NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                changes JSON NOT NULL,
                actor_user_id VARCHAR(64) NOT NULL,
                occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_order_audit_log_order_id ON order_audit_log (order_id)');
        $this->addSql('CREATE INDEX idx_order_audit_log_partner_order ON order_audit_log (partner_id, order_id_value)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE order_audit_log');
    }
}
