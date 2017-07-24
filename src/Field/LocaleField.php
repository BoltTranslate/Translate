<?php

namespace Bolt\Extension\Animal\Translate\Field;

use Bolt\Storage\Field\FieldInterface;
use Bolt\Storage\Field\Type\FieldTypeBase;

class LocaleField extends FieldTypeBase implements FieldInterface
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
}
