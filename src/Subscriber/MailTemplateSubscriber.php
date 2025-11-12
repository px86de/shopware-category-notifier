<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Subscriber;

use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailTemplateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $mailTemplateRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'onMailBeforeValidate',
        ];
    }

    public function onMailBeforeValidate(MailBeforeValidateEvent $event): void
    {
        $templateData = $event->getTemplateData();
        
        // Prüfen, ob es eine unserer Templates ist
        if (!isset($templateData['_technical_name'])) {
            return;
        }

        $technicalName = $templateData['_technical_name'];
        
        if ($technicalName !== 'px86_category_notifier.confirmation' 
            && $technicalName !== 'px86_category_notifier.new_product') {
            return;
        }

        // Template laden
        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        $mailTemplate = $this->mailTemplateRepository->search($criteria, $event->getContext())->first();
        
        if (!$mailTemplate) {
            return;
        }

        // Template-Daten setzen
        $event->addTemplateData('mailTemplate', $mailTemplate);
    }
}
