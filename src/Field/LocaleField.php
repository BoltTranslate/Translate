<?php

namespace Bolt\Extension\Animal\Translate\Field;

use Bolt\Storage\EntityManager;
use Bolt\Storage\Field\Type\FieldTypeBase;
use Bolt\Storage\QuerySet;

class LocaleField extends FieldTypeBase
{
    public function persist(QuerySet $queries, $entity, EntityManager $em = null)
    {
        /*$queries[0]->setParameter('title', 'test1');
        dump($queries, $entity, $em);
        die();*/
    }
    public function hydrate($data, $entity)
    {
        
        
        /*$this->set($entity, $value);
        dump($this, $data, $entity);*/
    }

    public function getName()
    {
        return 'locale';
    }

    public function getTemplate()
    {
        return 'fields/_locale.twig';
    }

    public function getStorageType()
    {
        return 'string';
    }

    public function getStorageOptions()
    {
        return ['default' => ''];
    }
}
