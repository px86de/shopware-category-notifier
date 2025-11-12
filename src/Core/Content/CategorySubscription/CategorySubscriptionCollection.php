<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Core\Content\CategorySubscription;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(CategorySubscriptionEntity $entity)
 * @method void                          set(string $key, CategorySubscriptionEntity $entity)
 * @method CategorySubscriptionEntity[]    getIterator()
 * @method CategorySubscriptionEntity[]    getElements()
 * @method CategorySubscriptionEntity|null get(string $key)
 * @method CategorySubscriptionEntity|null first()
 * @method CategorySubscriptionEntity|null last()
 */
class CategorySubscriptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CategorySubscriptionEntity::class;
    }
}
