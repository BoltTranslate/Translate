<?php

namespace Bolt\Extension\Animal\Translate\Provider;

use Bolt\Extension\Animal\Translate\Field\LocaleField;
use Bolt\Storage\FieldManager;
use Silex\Application;
use Silex\ServiceProviderInterface;

class FieldProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['storage.typemap'] = array_merge(
            $app['storage.typemap'],
            [
                'locale' => LocaleField::class
            ]
        );

        $app['storage.field_manager'] = $app->share(
            $app->extend(
                'storage.field_manager',
                function (FieldManager $manager) {
                    $manager->addFieldType('locale', new LocaleField());

                    return $manager;
                }
            )
        );

    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}