<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731420000RenameTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731420000;
    }

    public function update(Connection $connection): void
    {
        // Prüfen ob alte Tabelle existiert
        $oldTableExists = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() 
             AND table_name = 'swag_category_notifier_subscription'"
        );

        if ($oldTableExists) {
            // Tabelle umbenennen
            $connection->executeStatement(
                'RENAME TABLE swag_category_notifier_subscription TO px86_category_notifier_subscription'
            );
        }

        // Mail Template Types aktualisieren
        $connection->executeStatement(
            "UPDATE mail_template_type 
             SET technical_name = 'px86_category_notifier.confirmation' 
             WHERE technical_name = 'swag_category_notifier.confirmation'"
        );

        $connection->executeStatement(
            "UPDATE mail_template_type 
             SET technical_name = 'px86_category_notifier.new_product' 
             WHERE technical_name = 'swag_category_notifier.new_product'"
        );

        // Snippet Author aktualisieren
        $connection->executeStatement(
            "UPDATE snippet 
             SET author = 'Px86CategoryNotifier' 
             WHERE author = 'SwagCategoryNotifier'"
        );

        // System Config aktualisieren
        $connection->executeStatement(
            "UPDATE system_config 
             SET configuration_key = REPLACE(configuration_key, 'SwagCategoryNotifier', 'Px86CategoryNotifier') 
             WHERE configuration_key LIKE 'SwagCategoryNotifier%'"
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nichts zu tun
    }
}
