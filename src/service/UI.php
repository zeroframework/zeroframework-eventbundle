<?php

namespace service;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

use Entity\Component;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Adibox\Bundle\RenderBundle\Exception\HumanException;
use Twig_Extension;
use Symfony\Component\HttpFoundation\Response;
use Adibox\Bundle\RenderBundle\Exception\ExceptionWithResponse;

/**
 * Description of UI
 *
 * @author adibox
 */
class UI extends \Twig_Extension {

    private $eventClientService;
    private $container;

    const TYPE_DATA = 1;
    const TYPE_URL = 2;
    const SUCCESS = 1;
    const ERROR = 2;
    const ICON_LIST = "ui-icon-list";

    private $tabs = array();

    public function __construct($eventClientService, $container) {
        $this->eventClientService = $eventClientService;
        $this->container = $container;
        /*
        if(
            $this->container->isScopeActive("request")
            &&
            $this->container->hasScope("request")
        )
        {
        */
            $eventClientService->connect();
        //}
    }

    public function isSocket()
    {
        return $this->eventClientService->isSocket();
    }

    public function connect()
    {
        return $this->eventClientService->connect();
    }

    public function getName() {
        return 'render.ui.twig';
    }

    public function getGlobals() {
        return array(
            'tabs' => $this->getTabs(),
            'experimented' => false,//($this->container->get("request")->server->get("SERVER_NAME") == "adibox2") ? true : false
        );
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getTabs', array($this, "getTabs")),
        );
    }

    public function getTabs() {
        return $this->tabs;
    }

    public function flash($type, $title, $message, $clients = array()) {
        switch ($type) {
            case UI::SUCCESS:
                $this->eventClientService->notify("flash", array(
                    "type" => "success",
                    "title" => $title,
                    "message" => $message
                ), $clients);
                break;

            case UI::ERROR:
                $this->eventClientService->notify("flash", array(
                    "type" => "error",
                    "title" => $title,
                    "message" => $message
                ), $clients);
                break;
        }
    }

    public function updateComponent(Component $component, $clients = array())
    {
        $this->eventClientService->notify(
            "component.update",
            array("id" => $component->getId()),
            $clients
        );
    }

    protected function renderJson($data = array()) {
        return new Response(json_encode($data), 200, array('Content-Type' => 'application/json'));
    }

    protected function returnAjax($data = array(), $code = "success") {
        return $this->renderJson(array(
                    'code' => 'success',
                    'data' => $data
                ));
    }
}

?>
