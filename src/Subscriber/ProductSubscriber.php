<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Subscriber;

use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Px86\CategoryNotifier\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categorySubscriptionRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            'product_category.written' => 'onProductCategoryWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        

        foreach ($event->getWriteResults() as $result) {
            $operation = $result->getOperation();
            
            // Nur bei INSERT (neue Produkte)
            if ($operation !== 'insert') {
                continue;
            }
            
            $productId = $result->getPrimaryKey();
            
            $criteria = new Criteria([$productId]);
            $criteria->addAssociation('categories');
            
            $product = $this->productRepository->search($criteria, $context)->first();
            
            if (!$product || !$product->getCategories()) {
                continue;
            }


            foreach ($product->getCategories() as $category) {
                $this->notifySubscribers($category->getId(), $product, $context);
            }
        }
    }

    public function onProductCategoryWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        
        
        // Für jede neue Produkt-Kategorie-Zuordnung
        foreach ($event->getWriteResults() as $result) {
            // Nur bei neuen Zuordnungen (insert)
            if ($result->getOperation() !== 'insert') {
                continue;
            }
            
            $payload = $result->getPayload();
            
            if (!isset($payload['productId']) || !isset($payload['categoryId'])) {
                continue;
            }
            
            $productId = $payload['productId'];
            $categoryId = $payload['categoryId'];
            
            
            // Produkt laden mit createdAt
            $criteria = new Criteria([$productId]);
            $product = $this->productRepository->search($criteria, $context)->first();
            
            if (!$product) {
                continue;
            }
            
            // Wenn Produkt gerade erstellt wurde (< 5 Sekunden), überspringen
            // (wird bereits von onProductWritten behandelt)
            $createdAt = $product->getCreatedAt();
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $createdAt->getTimestamp();
            
            if ($diff < 5) {
                continue;
            }
            
            $this->notifySubscribers($categoryId, $product, $context);
        }
    }

    private function notifySubscribers(string $categoryId, $product, Context $context): void
    {
        $this->logger->info('Category Notifier: Checking subscriptions for category', ['categoryId' => $categoryId, 'productId' => $product->getId()]);
        
        // Alle aktiven und bestätigten Abonnements für diese Kategorie laden
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        $criteria->addFilter(new EqualsFilter('confirmed', true));
        $criteria->addFilter(new EqualsFilter('active', true));

        $subscriptions = $this->categorySubscriptionRepository->search($criteria, $context);

        $this->logger->info('Category Notifier: Found subscriptions', ['count' => $subscriptions->count()]);

        if ($subscriptions->count() === 0) {
            return;
        }

        // Benachrichtigungen versenden
        foreach ($subscriptions->getElements() as $subscription) {
            try {
                $this->logger->info('Category Notifier: Sending notification to', ['email' => $subscription->getEmail()]);
                $this->notificationService->sendNewProductNotification($subscription, $product, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send product notification', [
                    'productId' => $product->getId(),
                    'categoryId' => $categoryId,
                    'subscriptionEmail' => $subscription->getEmail(),
                    'exception' => $e->getMessage()
                ]);
            }
        }
    }
}
