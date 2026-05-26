<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_products tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE orders (
                id UUID NOT NULL,
                partner_id VARCHAR(64) NOT NULL,
                order_id VARCHAR(64) NOT NULL,
                expected_delivery_date DATE NOT NULL,
                total_value NUMERIC(14, 2) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX orders_partner_order_unique ON orders (partner_id, order_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE order_products (
                id UUID NOT NULL,
                order_id UUID NOT NULL,
                product_id VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                price NUMERIC(14, 2) NOT NULL,
                quantity INT NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_order_products_order_id ON order_products (order_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE order_products
            ADD CONSTRAINT fk_order_products_order
            FOREIGN KEY (order_id) REFERENCES orders (id)
            ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_products DROP CONSTRAINT fk_order_products_order');
        $this->addSql('DROP TABLE order_products');
        $this->addSql('DROP TABLE orders');
    }
}
