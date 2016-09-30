<?php

namespace Bolt\Extension\Animal\Translate\Field;

use Bolt\Storage\Field\FieldInterface;

class LocaleDataField implements FieldInterface
{
    public function getName()
    {
        return 'locale_data';
    }

    public function getTemplate()
    {
        return 'fields/_locale_data.twig';
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
