<?php

namespace Bolt\Extension\Animal\Translate\Field;

use Bolt\Storage\Field\FieldInterface;

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
        return 'string';
    }

    public function getStorageOptions()
    {
        return ['default' => ''];
    }

    public function load()
    {
        return null;
    }
}
