<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $salutationRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
        ];
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('salutationKey', FieldSorting::ASCENDING));

        $salutations = $this->salutationRepository->search($criteria, $context->getContext())->getEntities();

        $page->addExtension('salutations', $salutations);
    }
}
