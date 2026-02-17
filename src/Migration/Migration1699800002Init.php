<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1699800002Init extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699800002;
    }

    public function update(Connection $connection): void
    {
        $this->createSubscriptionTable($connection);
        $this->createConfirmationMailTemplate($connection);
        $this->createNewProductMailTemplate($connection);
        $this->importSnippets($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createSubscriptionTable(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `px86_category_notifier_subscription` (
    `id` BINARY(16) NOT NULL,
    `sales_channel_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
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
        ON UPDATE CASCADE,
    CONSTRAINT `fk.px86_category_notifier_subscription.language_id`
        FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk.px86_category_notifier_subscription.sales_channel_id`
        FOREIGN KEY (`sales_channel_id`)
        REFERENCES `sales_channel` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    private function createConfirmationMailTemplate(Connection $connection): void
    {
        $mailTemplateTypeId = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => 'px86_category_notifier.confirmation']
        );

        if (!$mailTemplateTypeId) {
            $mailTemplateTypeId = Uuid::randomBytes();
            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeId,
                'technical_name' => 'px86_category_notifier.confirmation',
                'available_entities' => json_encode([
                    'subscription' => 'px86_category_notifier_subscription',
                    'salesChannel' => 'sales_channel'
                ]),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            // Translations for Type
            $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
            if ($languageIdDe) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'language_id' => $languageIdDe,
                    'name' => 'Kategorie-Benachrichtigung: Bestätigung',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
            if ($languageIdEn) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'language_id' => $languageIdEn,
                    'name' => 'Category Notification: Confirmation',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }
        }

        // Check if Mail Template exists
        $mailTemplateId = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId AND system_default = 1',
            ['typeId' => $mailTemplateTypeId]
        );

        if ($mailTemplateId) {
            return;
        }

        $mailTemplateId = Uuid::randomBytes();
        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
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

        $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
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
        $mailTemplateTypeId = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => 'px86_category_notifier.new_product']
        );
        
        if (!$mailTemplateTypeId) {
            $mailTemplateTypeId = Uuid::randomBytes();
            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeId,
                'technical_name' => 'px86_category_notifier.new_product',
                'available_entities' => json_encode([
                    'subscription' => 'px86_category_notifier_subscription',
                    'products' => 'product', // Collection wird in SW6 oft als Array übergeben, Typisierung hier ist eher Info
                    'salesChannel' => 'sales_channel'
                ]),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            // Translation DE
            $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
            if ($languageIdDe) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'language_id' => $languageIdDe,
                    'name' => 'Kategorie-Benachrichtigung: Neue Produkte',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            // Translation EN
            $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
            if ($languageIdEn) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'language_id' => $languageIdEn,
                    'name' => 'Category Notification: New Products',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }
        }

        // Check if Mail Template exists
        $mailTemplateId = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId AND system_default = 1',
            ['typeId' => $mailTemplateTypeId]
        );

        if ($mailTemplateId) {
            return;
        }

        $mailTemplateId = Uuid::randomBytes();
        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $languageIdDe = $this->getLanguageIdByLocale($connection, 'de-DE');
        if ($languageIdDe) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdDe,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Neue Produkte in Ihrer abonnierten Kategorie',
                'description' => 'Benachrichtigung über neue Produkte',
                'content_html' => $this->getNewProductContentHtmlDe(),
                'content_plain' => $this->getNewProductContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $languageIdEn = $this->getLanguageIdByLocale($connection, 'en-GB');
        if ($languageIdEn) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languageIdEn,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'New products in your subscribed category',
                'description' => 'Notification about new products',
                'content_html' => $this->getNewProductContentHtmlEn(),
                'content_plain' => $this->getNewProductContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = "SELECT language.id FROM language 
                INNER JOIN locale ON locale.id = language.translation_code_id 
                WHERE locale.code = :locale";
        
        $languageId = $connection->fetchOne($sql, ['locale' => $locale]);
        return $languageId ? (string) $languageId : null;
    }

    private function importSnippets(Connection $connection): void
    {
        // Simple Snippet loader logic
        $files = [
            'de-DE' => __DIR__ . '/../Resources/snippet/de_DE/messages.de-DE.json',
            'en-GB' => __DIR__ . '/../Resources/snippet/en_GB/messages.en-GB.json'
        ];

        foreach ($files as $locale => $file) {
            if (!file_exists($file)) {
                continue;
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);
            if (!$data) continue;

            $flattened = $this->flattenArray($data);
            
            $snippetSetIds = $connection->fetchFirstColumn(
                "SELECT LOWER(HEX(id)) FROM snippet_set WHERE iso = :iso",
                ['iso' => $locale]
            );

            foreach ($snippetSetIds as $setId) {
                foreach ($flattened as $key => $value) {
                     $connection->executeStatement(
                        'INSERT IGNORE INTO snippet (id, snippet_set_id, translation_key, value, author, created_at) 
                         VALUES (:id, UNHEX(:setId), :key, :value, :author, :createdAt)
                         ON DUPLICATE KEY UPDATE value = :value',
                        [
                            'id' => Uuid::randomBytes(),
                            'setId' => $setId,
                            'key' => $key,
                            'value' => $value,
                            'author' => 'Px86CategoryNotifier',
                            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                        ]
                    );
                }
            }
        }
    }
    
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    // --- Content Helfer ---

    private function getConfirmationContentHtmlDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {% if salutation is defined and salutation is not null %}
            Hallo {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
        {% else %}
            Hallo,
        {% endif %}
    </p>
    <p>
        vielen Dank für Ihre Anmeldung zum Newsletter für die Kategorie "{{ category.translated.name ?? category.name }}".
    </p>
    <p>
        Um die Anmeldung abzuschließen, klicken Sie bitte auf folgenden Link:
        <br><br>
        <a href="{{ confirmUrl }}">{{ confirmUrl }}</a>
    </p>
</div>';
    }

    private function getConfirmationContentPlainDe(): string
    {
        return '{% if salutation is defined and salutation is not null %}
Hallo {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
{% else %}
Hallo,
{% endif %}

vielen Dank für Ihre Anmeldung zum Newsletter für die Kategorie "{{ category.translated.name ?? category.name }}".

Um die Anmeldung abzuschließen, klicken Sie bitte auf folgenden Link:

{{ confirmUrl }}';
    }

    private function getConfirmationContentHtmlEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {% if salutation is defined and salutation is not null %}
            Hello {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
        {% else %}
            Hello,
        {% endif %}
    </p>
    <p>
        thank you for subscribing to the newsletter for the category "{{ category.translated.name ?? category.name }}".
    </p>
    <p>
        To complete your subscription, please click on the following link:
        <br><br>
        <a href="{{ confirmUrl }}">{{ confirmUrl }}</a>
    </p>
</div>';
    }

    private function getConfirmationContentPlainEn(): string
    {
        return '{% if salutation is defined and salutation is not null %}
Hello {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
{% else %}
Hello,
{% endif %}

thank you for subscribing to the newsletter for the category "{{ category.translated.name ?? category.name }}".

To complete your subscription, please click on the following link:

{{ confirmUrl }}';
    }

    private function getNewProductContentHtmlDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
         {% if salutation is defined and salutation is not null %}
            Hallo {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
        {% else %}
            Hallo,
        {% endif %}
    </p>
    <p>
        es gibt neue Produkte in der Kategorie "{{ category.translated.name ?? category.name }}":
    </p>
    <ul>
        <li><a href="{{ productUrl }}">{{ product.translated.name ?? product.name }}</a></li>
    </ul>
    <p>
        <a href="{{ unsubscribeUrl }}">Benachrichtigungen abbestellen</a>
    </p>
</div>';
    }

    private function getNewProductContentPlainDe(): string
    {
        return '{% if salutation is defined and salutation is not null %}
Hallo {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
{% else %}
Hallo,
{% endif %}

es gibt neue Produkte in der Kategorie "{{ category.translated.name ?? category.name }}":

- {{ product.translated.name ?? product.name }} ({{ productUrl }})

Benachrichtigungen abbestellen: {{ unsubscribeUrl }}';
    }

    private function getNewProductContentHtmlEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {% if salutation is defined and salutation is not null %}
            Hello {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
        {% else %}
            Hello,
        {% endif %}
    </p>
    <p>
        there are new products in the category "{{ category.translated.name ?? category.name }}":
    </p>
    <ul>
        <li><a href="{{ productUrl }}">{{ product.translated.name ?? product.name }}</a></li>
    </ul>
    <p>
        <a href="{{ unsubscribeUrl }}">Unsubscribe from notifications</a>
    </p>
</div>';
    }

    private function getNewProductContentPlainEn(): string
    {
        return '{% if salutation is defined and salutation is not null %}
Hello {{ salutation.translated.letterName }} {{ firstName }} {{ lastName }},
{% else %}
Hello,
{% endif %}

there are new products in the category "{{ category.translated.name ?? category.name }}":

- {{ product.translated.name ?? product.name }} ({{ productUrl }})

Unsubscribe from notifications: {{ unsubscribeUrl }}';
    }
}
