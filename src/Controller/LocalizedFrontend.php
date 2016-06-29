<?php

namespace Bolt\Extension\Animal\Translate\Controller;

use Bolt\Controller\Zone;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class LocalizedFrontend implements ControllerProviderInterface
{
    private $config;

    /**
     * Controller constructor
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        /** @var $ctr ControllerCollection */
        $ctr = $app['controllers_factory'];
        $ctr->value(Zone::KEY, Zone::FRONTEND);

        $ctr->match('example/{id}', [$this, 'example'])
            ->bind('example')
            ->method(Request::METHOD_GET);

        return $ctr;
    }


    public function example(Request $request, Application $app, $id)
    {

    }
}
