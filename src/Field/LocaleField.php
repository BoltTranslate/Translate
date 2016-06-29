<?php

namespace Bolt\Extension\Animal\Translate\Field;

use Bolt\Field\FieldInterface;

class LocaleField implements FieldInterface
{

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
        return 'text';
    }

    public function getStorageOptions()
    {
        return ['default' => ''];
    }

}