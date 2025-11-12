<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731312000CreateCategorySubscriptionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731312000;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `px86_category_notifier_subscription` (
    `id` BINARY(16) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `category_id` BINARY(16) NOT NULL,
    `salutation_id` BINARY(16) NULL,
    `first_name` VARCHAR(255) NULL,
    `last_name` VARCHAR(255) NULL,
    `confirmed` TINYINT(1) NOT NULL DEFAULT 0,
    `confirm_token` VARCHAR(255) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.email_category` (`email`, `category_id`),
    KEY `idx.category_id` (`category_id`),
    KEY `idx.confirm_token` (`confirm_token`),
    CONSTRAINT `fk.px86_category_notifier_subscription.category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk.px86_category_notifier_subscription.salutation_id`
        FOREIGN KEY (`salutation_id`)
        REFERENCES `salutation` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
