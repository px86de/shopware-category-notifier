<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Service;

use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Px86\CategoryNotifier\Core\Content\CategorySubscription\CategorySubscriptionEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly RouterInterface $router,
        private readonly MailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    public function sendNewProductNotification(
        CategorySubscriptionEntity $subscription,
        ProductEntity $product,
        Context $context
    ): void {
        // Kategorie laden
        $category = $this->categoryRepository->search(
            new Criteria([$subscription->getCategoryId()]),
            $context
        )->first();

        if (!$category) {
            return;
        }

        // SalesChannel laden
        $salesChannel = $this->getSalesChannel($context);
        if (!$salesChannel) {
            return;
        }

        // Product URL generieren
        $productUrl = $this->router->generate(
            'frontend.detail.page',
            ['productId' => $product->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Unsubscribe URL generieren
        $unsubscribeUrl = $this->router->generate(
            'px86_category_notifier_unsubscribe',
            ['email' => $subscription->getEmail(), 'categoryId' => $subscription->getCategoryId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Mail-Template laden
        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'px86_category_notifier.new_product'));
        $criteria->setLimit(1);
        
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();
        
        if (!$mailTemplate) {
            return;
        }

        $data = [
            'recipients' => [
                $subscription->getEmail() => $subscription->getFirstName() 
                    ? $subscription->getFirstName() . ' ' . $subscription->getLastName() 
                    : $subscription->getEmail()
            ],
            'salesChannelId' => $salesChannel->getId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => [],
            'contentHtml' => $mailTemplate->getContentHtml(),
            'contentPlain' => $mailTemplate->getContentPlain(),
            'subject' => $mailTemplate->getSubject(),
            'senderName' => $mailTemplate->getSenderName() ?? $salesChannel->getName(),
        ];

        $templateData = [
            'subscription' => [
                'firstName' => $subscription->getFirstName(),
                'lastName' => $subscription->getLastName(),
                'email' => $subscription->getEmail(),
            ],
            'product' => [
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'id' => $product->getId(),
            ],
            'category' => [
                'name' => $category->getName(),
                'id' => $category->getId(),
            ],
            'productUrl' => $productUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
            'salesChannel' => $salesChannel,
        ];

        $this->mailService->send($data, $context, $templateData);
    }

    public function sendConfirmationEmail(
        string $email,
        string $confirmToken,
        ?string $firstName,
        Context $context
    ): void {
        // SalesChannel laden
        $salesChannel = $this->getSalesChannel($context);
        if (!$salesChannel) {
            return;
        }

        // Bestätigungs-URL generieren
        $confirmUrl = $this->router->generate(
            'px86_category_notifier_confirm',
            ['token' => $confirmToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Mail-Template laden
        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'px86_category_notifier.confirmation'));
        $criteria->setLimit(1);
        
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();
        
        if (!$mailTemplate) {
            return;
        }

        $data = [
            'recipients' => [$email => $firstName ?? $email],
            'salesChannelId' => $salesChannel->getId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => [],
            'contentHtml' => $mailTemplate->getContentHtml(),
            'contentPlain' => $mailTemplate->getContentPlain(),
            'subject' => $mailTemplate->getSubject(),
            'senderName' => $mailTemplate->getSenderName() ?? $salesChannel->getName(),
        ];

        $templateData = [
            'subscription' => [
                'email' => $email,
                'firstName' => $firstName,
                'confirmToken' => $confirmToken,
            ],
            'confirmUrl' => $confirmUrl,
            'salesChannel' => $salesChannel,
        ];

        $this->mailService->send($data, $context, $templateData);
    }

    private function getSalesChannel(Context $context)
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));
        
        return $this->salesChannelRepository->search($criteria, $context)->first();
    }
}
