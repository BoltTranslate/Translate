<?php

namespace Bolt\Extension\Animal\Translate\EventListener;

use Bolt\Config as BoltConfig;
use Bolt\Events\HydrationEvent;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\Animal\Translate\Config\Config;
use Bolt\Storage\Entity\Content;
use Bolt\Storage\Field\Collection\RepeatingFieldCollection;
use Bolt\Storage\Query\Query;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Storage event subscriber.
 *
 * @author Svante Richter <svante.richter@gmail.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class StorageListener implements EventSubscriberInterface
{
    /** @var BoltConfig */
    private $boltConfig;
    /** @var Query $query */
    private $query;
    /** @var RequestStack */
    private $requestStack;
    /** @var array */
    private $config;

    /**
     * Constructor.
     *
     * @param BoltConfig   $boltConfig
     * @param Config       $config
     * @param Query        $query
     * @param RequestStack $requestStack
     */
    public function __construct(BoltConfig $boltConfig, Config $config, Query $query, RequestStack $requestStack)
    {
        $this->boltConfig = $boltConfig;
        $this->config = $config;
        $this->query = $query;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            StorageEvents::PRE_HYDRATE => [
                ['preHydrate', 0],
            ],
            StorageEvents::POST_HYDRATE => [
                ['postHydrate', 0],
            ],
            StorageEvents::PRE_SAVE => [
                ['preSave', 0],
            ],
            StorageEvents::POST_SAVE => [
                ['postSave', 0],
            ],
        ];
    }

    /**
     * StorageEvents::PRE_HYDRATE event callback.
     *
     * @param HydrationEvent $event
     */
    public function preHydrate(HydrationEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $localeSlug = $request->get('_locale');

        /** @var Content $entity */
        $entity = $event->getArgument('entity');
        $subject = $event->getSubject();

        if (!$entity instanceof Content || $request->request->getBoolean('no_locale_hydrate')) {
            return;
        }

        $contentTypeName = $entity->getContenttype();
        $contentType = $this->boltConfig->get('contenttypes/' . $contentTypeName);

        if (isset($subject[$localeSlug . 'data'])) {
            $localeData = json_decode($subject[$localeSlug . 'data'], true);
            foreach ($localeData as $key => $value) {
                if ($contentType['fields'][$key]['type'] !== 'repeater') {
                    $subject[$key] = is_array($value) ? json_encode($value) : $value;
                }
            }
        }
    }

    /**
     * StorageEvents::POST_HYDRATE event callback.
     *
     * @param HydrationEvent $event
     */
    public function postHydrate(HydrationEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $localeSlug = $request->get('_locale');

        /** @var Content $subject */
        $subject = $event->getSubject();
        if (!$subject instanceof Content || $request->request->getBoolean('no_locale_hydrate')) {
            return;
        }
        $contentTypeName = $subject->getContenttype();
        $contentType = $this->boltConfig->get('contenttypes/' . $contentTypeName);

        if (!isset($subject[$localeSlug . 'data'])) {
            return;
        }
        $localeData = json_decode($subject[$localeSlug . 'data'], true);
        foreach ($localeData as $key => $value) {
            if ($key === 'templatefields') {
                $templateFields = $this->boltConfig->get('theme/templatefields/' . $subject['template'] . '/fields');
                foreach ($templateFields as $key => $field) {
                    if ($field['type'] === 'repeater') {
                        $repeaterData = json_decode($value[$key], true);
                        /** @var RepeatingFieldCollection[] $subject */
                        $subject['templatefields'][$key]->clear();
                        foreach ($repeaterData as $subValue) {
                            $subject['templatefields'][$key]->addFromArray($subValue);
                        }
                    }
                }
            }
        
            if ($contentType['fields'][$key]['type'] !== 'repeater') {
                continue;
            }
            /** @var RepeatingFieldCollection[] $subject */
            $subject[$key]->clear();
            foreach ($value as $subValue) {
                $subject[$key]->addFromArray($subValue);
            }
        }
    }

    /**
     * StorageEvents::PRE_SAVE event callback.
     *
     * @param StorageEvent $event
     */
    public function preSave(StorageEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $contentType = $this->boltConfig->get('contenttypes/' . $event->getContentType());
        $translatableFields = $this->getTranslatableFields($contentType['fields']);
        /** @var Content $record */
        $record = $event->getContent();
        $values = $record->serialize();
        $localeValues = [];

        if (empty($translatableFields)) {
            return;
        }

        $localeSlug = $request->get('_locale');

        $record->set($localeSlug . 'slug', $values['slug']);
        $locales = $this->config->getLocales();
        if ($values['_locale'] == reset($locales)->getSlug()) {
            $record->set($localeSlug . 'data', '[]');
            return;
        }

        if ($values['id']) {
            /** @var Content $defaultContent */
            $defaultContent = $this->query->getContent(
                $event->getContentType(),
                ['id' => $values['id'], 'returnsingle' => true]
            );
        }

        if (in_array('templatefields', $translatableFields)) {
            $templateFields = $this->boltConfig->get('theme/templatefields/' . $values['template'] . '/fields');
            foreach ($templateFields as $key => $field) {
                if ($field['type'] === 'repeater') {
                    $values['templatefields'][$key] = json_encode($values['templatefields'][$key]);
                }
            }
        }

        foreach ($translatableFields as $field) {
            $localeValues[$field] = $values[$field];
            if ($values['id']) {
                $record->set($field, $defaultContent->get($field));
            } else {
                $record->set($field, '');
            }
        }
        $localeJson = json_encode($localeValues);
        $record->set($localeSlug . 'data', $localeJson);
    }

    /**
     * StorageEvents::POST_SAVE event callback.
     *
     * @param StorageEvent $event
     */
    public function postSave(StorageEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $localeSlug = $request->get('_locale');
        $subject = $event->getSubject();

        if (!$subject instanceof Content) {
            return;
        }

        if (!isset($subject[$localeSlug . 'data'])) {
            return;
        }

        $localeData = json_decode($subject[$localeSlug . 'data']);
        foreach ($localeData as $key => $value) {
            $subject->set($key, $value);
        }
    }

    /**
     * Helper to check for translatable fields in a contenttype
     *
     * @param array $fields
     *
     * @return array
     */
    private function getTranslatableFields($fields)
    {
        $translatable = [];
        if (is_array($fields)) {
            foreach ($fields as $name => $field) {
                if (isset($field['is_translateable']) &&
                    $field['is_translateable'] === true &&
                    $field['type'] === 'templateselect'
                ) {
                    $translatable[] = 'templatefields';
                } elseif (isset($field['is_translateable']) && $field['is_translateable'] === true) {
                    $translatable[] = $name;
                }
            }
        }

        return $translatable;
    }
}
