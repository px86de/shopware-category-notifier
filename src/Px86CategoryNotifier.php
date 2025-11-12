<?php declare(strict_types=1);

namespace Px86\CategoryNotifier;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Doctrine\DBAL\Connection;

class Px86CategoryNotifier extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        
        // Mail-Templates löschen
        $connection->executeStatement("
            DELETE FROM mail_template_translation 
            WHERE mail_template_id IN (
                SELECT id FROM mail_template 
                WHERE mail_template_type_id IN (
                    SELECT id FROM mail_template_type 
                    WHERE technical_name LIKE 'px86_category_notifier%'
                )
            )
        ");
        
        $connection->executeStatement("
            DELETE FROM mail_template 
            WHERE mail_template_type_id IN (
                SELECT id FROM mail_template_type 
                WHERE technical_name LIKE 'px86_category_notifier%'
            )
        ");
        
        $connection->executeStatement("
            DELETE FROM mail_template_type_translation 
            WHERE mail_template_type_id IN (
                SELECT id FROM mail_template_type 
                WHERE technical_name LIKE 'px86_category_notifier%'
            )
        ");
        
        $connection->executeStatement("
            DELETE FROM mail_template_type 
            WHERE technical_name LIKE 'px86_category_notifier%'
        ");
        
        // Subscription-Tabelle löschen
        $connection->executeStatement('DROP TABLE IF EXISTS `px86_category_notifier_subscription`');
    }
}
