<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Legacy\Storage;
use Silex\Application;

class Legacy extends Storage
{
    public function getContentObject($contenttype, $values = [])
    {
        dump($values);
        return parent::getContentObject($contenttype, $values = []);        
    }
}