<?php
/**
 * Created by JetBrains PhpStorm.
 * User: adibox
 * Date: 19/09/13
 * Time: 10:08
 * To change this template use File | Settings | File Templates.
 */

namespace service;
use Adibox\Bundle\RenderBundle\Model\abstractController;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;


class EventSessionIdListener extends abstractController {

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    public function onChangeSessionId(UserInterface $user, $request = null)
    {
        $request = ($request === null) ? $this->container->get("request") : $request;

        $session = $request->getSession();

        if(null === $session) return;

        $em = $this->getEntityManager();

        $sessionId = $request->getSession()->getId();

        if($user->getLastSessionId() != $sessionId)
        {
            $user->setLastSessionId($request->getSession()->getId());

            $em->persist($user);

            $em->flush($user);
        }
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $user = $this->getUser();

        if(!is_object($user) || !$user instanceof UserInterface)
        {
            return;
        }

        $this->onChangeSessionId($user, $request);
    }

}