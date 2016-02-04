<?php

namespace Bolt\Extension\sahassar\translate\Field;

use Bolt\Field\FieldInterface;

class LocaleField implements FieldInterface
{    
    public function getName()
    {
        return 'locale';
    }

    public function getTemplate()
    {
        return 'field/_locale.twig';
    }

    public function getStorageType()
    {
        return 'text';
    }

    public function getStorageOptions()
    {
        return array('default' => '');
    }
}
