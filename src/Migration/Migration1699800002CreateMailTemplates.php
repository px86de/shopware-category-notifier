<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1699800002CreateMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699800002;
    }

    public function update(Connection $connection): void
    {
        $this->createConfirmationMailTemplate($connection);
        $this->createNewProductMailTemplate($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createConfirmationMailTemplate(Connection $connection): void
    {
        // Prüfen, ob Template schon existiert
        $exists = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => 'px86_category_notifier.confirmation']
        );
        
        if ($exists) {
            return; // Template existiert bereits
        }

        $mailTemplateTypeId = Uuid::randomBytes();
        $mailTemplateId = Uuid::randomBytes();

        // Mail Template Type
        $connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => 'px86_category_notifier.confirmation',
            'available_entities' => json_encode([
                'subscription' => 'swag_category_subscription',
                'salesChannel' => 'sales_channel'
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Mail Template Type Translation DE
        $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
        if ($languageIdDe) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languageIdDe,
                'name' => 'Kategorie-Benachrichtigung: Bestätigung',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template Type Translation EN
        $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
        if ($languageIdEn) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languageIdEn,
                'name' => 'Category Notification: Confirmation',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template
        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Mail Template Translation DE
        if ($languageIdDe) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdDe,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Bitte bestätigen Sie Ihre E-Mail-Adresse',
                'description' => 'Bestätigungs-E-Mail für Kategorie-Benachrichtigungen',
                'content_html' => $this->getConfirmationContentHtmlDe(),
                'content_plain' => $this->getConfirmationContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template Translation EN
        if ($languageIdEn) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdEn,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Please confirm your email address',
                'description' => 'Confirmation email for category notifications',
                'content_html' => $this->getConfirmationContentHtmlEn(),
                'content_plain' => $this->getConfirmationContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function createNewProductMailTemplate(Connection $connection): void
    {
        // Prüfen, ob Template schon existiert
        $exists = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => 'px86_category_notifier.new_product']
        );
        
        if ($exists) {
            return; // Template existiert bereits
        }

        $mailTemplateTypeId = Uuid::randomBytes();
        $mailTemplateId = Uuid::randomBytes();

        // Mail Template Type
        $connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => 'px86_category_notifier.new_product',
            'available_entities' => json_encode([
                'subscription' => 'swag_category_subscription',
                'product' => 'product',
                'category' => 'category',
                'salesChannel' => 'sales_channel'
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Mail Template Type Translation DE
        $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
        if ($languageIdDe) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languageIdDe,
                'name' => 'Kategorie-Benachrichtigung: Neues Produkt',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template Type Translation EN
        $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
        if ($languageIdEn) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languageIdEn,
                'name' => 'Category Notification: New Product',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template
        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Mail Template Translation DE
        if ($languageIdDe) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdDe,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Neues Produkt in {{ category.name }}',
                'description' => 'Benachrichtigung über ein neues Produkt in einer abonnierten Kategorie',
                'content_html' => $this->getNewProductContentHtmlDe(),
                'content_plain' => $this->getNewProductContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Mail Template Translation EN
        if ($languageIdEn) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdEn,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'New product in {{ category.name }}',
                'description' => 'Notification about a new product in a subscribed category',
                'content_html' => $this->getNewProductContentHtmlEn(),
                'content_plain' => $this->getNewProductContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
            SELECT language.id
            FROM language
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE locale.code = :code
        SQL;

        $languageId = $connection->fetchOne($sql, ['code' => $locale]);

        return $languageId ?: null;
    }

    private function getConfirmationContentHtmlDe(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/views/mail/html/category-subscription-confirmation.html.twig');
    }

    private function getConfirmationContentPlainDe(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/views/mail/text/category-subscription-confirmation.text.twig');
    }

    private function getConfirmationContentHtmlEn(): string
    {
        return $this->getConfirmationContentHtmlDe(); // Kann später angepasst werden
    }

    private function getConfirmationContentPlainEn(): string
    {
        return $this->getConfirmationContentPlainDe(); // Kann später angepasst werden
    }

    private function getNewProductContentHtmlDe(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/views/mail/html/category-new-product.html.twig');
    }

    private function getNewProductContentPlainDe(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/views/mail/text/category-new-product.text.twig');
    }

    private function getNewProductContentHtmlEn(): string
    {
        return $this->getNewProductContentHtmlDe(); // Kann später angepasst werden
    }

    private function getNewProductContentPlainEn(): string
    {
        return $this->getNewProductContentPlainDe(); // Kann später angepasst werden
    }
}
