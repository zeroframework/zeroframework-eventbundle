<?php

namespace service;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

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

    public function openTabFromUrl($name, $icon, $url, $trigger, $clients = array()) {
        $data = array(
            "name" => $name,
            "icon" => $icon,
            "url" => $url,
            "trigger" => $trigger
        );

        if ($this->container->get("request")->isXmlHttpRequest()) {
            $this->eventClientService->notify("openTabFromUrl", $data, $clients);
        } else {
            $this->tabs[] = $data;
        }
    }

    public function reloadTab($clients = array()) {
        $this->eventClientService->notify("reloadTab", array(), $clients);
    }

    public function redirect($url) {
        $this->eventClientService->setHeader("X-location", $url);
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

    private function allowedViewRealException() {
        $clientIp = $this->container->get("request")->getClientIp();

        $ipAlloweds = array(
            "88.170.204.16",
            "127.0.0.1"
        );

        return (
                $this->container->get("kernel")->isDebug()
                ||
                in_array($clientIp, $ipAlloweds)
                );
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $exception = $event->getException();

        if (
                $exception instanceof HumanException
                ||
                $this->allowedViewRealException()
        ) {
            $this->eventClientService->setHeader("ignore-ajax-error", "1");

            //$this->flash(UI::ERROR, "", $this->container->get("translator")->trans($exception->getMessage(), array(), "humanException"));

            $html = "" .
                    "<div class='modal' id='container-exception'>" .
                    "    <h1 class='modal-header' style='margin: 0px;'>Oups !</h1>" .
                    "    <div class='modal-body'>" .
                    "    <h3>" .
                    /** @Ignore */
                    $this->container->get("translator")->trans($exception->getMessage(), array(), "humanException") . "</h3>" .
                    "    </div>" .
                    "    <div class='modal-footer'>" .
                    "        <button type='button' class='btn event' data-event='sos' data-message='" .
                    htmlentities(
                            /** @Ignore */
                            $this->container->get("translator")->trans($exception->getMessage(), array(), "humanException")) . "'>J'ai besoin d'aide</button>" .
                    "        <button type='button' class='btn btn-success' data-dismiss='modal'>J'ai bien compris</button>" .
                    "    </div>" .
                    "</div>";

            $this->createModal(UI::TYPE_DATA, utf8_encode($html), "", array(
                "attachTo" => "#flash_modal"
            ));

            if ($exception instanceof ExceptionWithResponse && $exception->isResponse())
                $event->setResponse($exception->getResponse());

            if (
                    !$this->container->get("kernel")->isDebug()
                    &&
                    !$this->container->get("request")->isXmlHttpRequest()
            ) {
                $realException = null;

                if ($this->allowedViewRealException()) {
                    $realException = ($exception instanceof HumanException) ? $exception->getRealException() : $exception;
                }

                $response = new Response();
                $response->setContent($this->container->get("templating")->render("TwigBundle:Exception:error.html.twig", array("status_code" => $exception->getCode(), "status_text" => $exception->getMessage(), "realException" => $realException)));

                // HttpExceptionInterface est un type d'exception spécial qui
                // contient le code statut et les détails de l'entête
                if ($exception instanceof HttpExceptionInterface) {
                    $response->setStatusCode($exception->getStatusCode());
                    $response->headers->replace($exception->getHeaders());
                } else {
                    $response->setStatusCode(500);
                }

                $event->setResponse($response);
            }
        }
    }

    public function onKernelView(GetResponseForControllerResultEvent $event) {
        $data = $event->getControllerResult();

        $event->stopPropagation();

        if ($data === null)
            return $this->returnAjax();
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

    public function closeTab($clients = array()) {
        $this->eventClientService->notify("closeTab", array(), $clients);
    }

    public function closeModal($clients = array()) {
        $this->eventClientService->notify("closeModal", array(), $clients);
    }

    public function createModal($source, $dataOrUrl, $trigger = "", $param = array(), $clients = array()) {
        switch ($source) {
            case UI::TYPE_DATA:
                $this->eventClientService->notify("modalCreate", array(
                    "source" => "data",
                    "data" => $dataOrUrl,
                    "trigger" => $trigger,
                    "parameters" => $param
                ), $clients);
                break;

            case UI::TYPE_URL:
                $this->eventClientService->notify("modalCreate", array(
                    "source" => "url",
                    "url" => $dataOrUrl,
                    "trigger" => $trigger,
                ), $clients);
                break;
        }
    }

}

?>
