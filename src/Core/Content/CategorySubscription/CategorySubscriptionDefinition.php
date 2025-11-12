<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Core\Content\CategorySubscription;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;

class CategorySubscriptionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'px86_category_notifier_subscription';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategorySubscriptionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategorySubscriptionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('email', 'email'))->addFlags(new Required()),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new Required()),
            new FkField('salutation_id', 'salutationId', SalutationDefinition::class),
            new StringField('first_name', 'firstName'),
            new StringField('last_name', 'lastName'),
            (new BoolField('confirmed', 'confirmed'))->addFlags(new Required()),
            new StringField('confirm_token', 'confirmToken'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false),
        ]);
    }
}
