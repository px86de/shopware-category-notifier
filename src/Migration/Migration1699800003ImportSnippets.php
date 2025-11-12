<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1699800003ImportSnippets extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699800003;
    }

    public function update(Connection $connection): void
    {
        // Lade Snippets aus JSON
        $deSnippets = $this->loadSnippets(__DIR__ . '/../Resources/snippet/de_DE/messages.de-DE.json');
        $enSnippets = $this->loadSnippets(__DIR__ . '/../Resources/snippet/en_GB/messages.en-GB.json');
        
        // Hole Sprachen-IDs
        $deLangId = $this->getLanguageId($connection, 'de-DE');
        $enLangId = $this->getLanguageId($connection, 'en-GB');
        
        // Snippet-Set IDs holen
        $deSnippetSetId = $this->getSnippetSetId($connection, 'de-DE');
        $enSnippetSetId = $this->getSnippetSetId($connection, 'en-GB');
        
        if ($deLangId && $deSnippetSetId) {
            $this->importSnippets($connection, $deSnippets, $deSnippetSetId, $deLangId, 'de-DE');
        }
        
        if ($enLangId && $enSnippetSetId) {
            $this->importSnippets($connection, $enSnippets, $enSnippetSetId, $enLangId, 'en-GB');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nichts zu tun
    }
    
    private function loadSnippets(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        
        return $this->flattenArray($data);
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
    
    private function getLanguageId(Connection $connection, string $locale): ?string
    {
        $sql = "SELECT LOWER(HEX(id)) as id FROM language WHERE LOWER(HEX(locale_id)) = (
            SELECT LOWER(HEX(id)) FROM locale WHERE code = :locale
        )";
        
        $result = $connection->fetchAssociative($sql, ['locale' => $locale]);
        
        return $result ? $result['id'] : null;
    }
    
    private function getSnippetSetId(Connection $connection, string $iso): ?string
    {
        $sql = "SELECT LOWER(HEX(id)) as id FROM snippet_set WHERE iso = :iso LIMIT 1";
        $result = $connection->fetchAssociative($sql, ['iso' => $iso]);
        
        return $result ? $result['id'] : null;
    }
    
    private function importSnippets(Connection $connection, array $snippets, string $snippetSetId, string $langId, string $locale): void
    {
        foreach ($snippets as $key => $value) {
            // Prüfen ob Snippet existiert
            $existing = $connection->fetchOne(
                'SELECT id FROM snippet WHERE `translation_key` = :key AND `snippet_set_id` = UNHEX(:setId)',
                ['key' => $key, 'setId' => $snippetSetId]
            );
            
            if (!$existing) {
                // Snippet einfügen
                $connection->insert('snippet', [
                    'id' => $connection->fetchOne('SELECT UNHEX(REPLACE(UUID(), "-", ""))'),
                    'snippet_set_id' => $connection->fetchOne('SELECT UNHEX(:id)', ['id' => $snippetSetId]),
                    'translation_key' => $key,
                    'value' => $value,
                    'author' => 'Px86CategoryNotifier',
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
