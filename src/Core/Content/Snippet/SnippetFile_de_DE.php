<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Core\Content\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'px86-category-notifier.de-DE';
    }

    public function getPath(): string
    {
        return __DIR__ . '/../../../Resources/snippet/de_DE/messages.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
    }

    public function getAuthor(): string
    {
        return 'Swag';
    }

    public function isBase(): bool
    {
        return false;
    }
}
