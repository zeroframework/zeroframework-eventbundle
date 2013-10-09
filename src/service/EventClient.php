<?php

namespace service;
use Adibox\Bundle\RenderBundle\Lib\lzw;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use ElephantIO\Client as Elephant;

/**
 * Description of EventClient
 *
 * @author adibox
 */
class EventClient {
    
    private $logger;
    
    private $events = array();

    private $headers = array();
    
    private $socket;

    private $enabled = true;

    private $securityContext;

    private $user = null;
    
    public function __construct($logger, $securityContext)
    {
        $this->logger = $logger;

        $this->socket = null;

        $this->securityContext = $securityContext;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getSecurityContext()
    {
        return $this->securityContext;
    }

    /**
     * Get a user from the Security Context
     *
     * @return mixed
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
    public function getUser()
    {
    }

    public function connect()
    {
        try {
            $this->socket = new Elephant("http://localhost:8765", "socket.io", 1, false, true, true);

            $this->getSocket()->init();
        }
        catch(\Exception $e)
        {
            throw new \Exception("Server eventlive driven not started 'nohup php app/console websocket::command '");
        }
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function enabled()
    {
        $this->enabled = true;
    }

    public function disabled()
    {
        $this->enabled = false;
    }

    public function isSocket()
    {
        return !empty($this->socket);
    }

    public function __destruct()
    {
        if($this->isSocket()) $this->getSocket()->close();
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }
    
    //put your code here
    public function notify($name, $parametres = array(), $clients = array())
    {
        if(!$this->isEnabled()) return;
        // $this->events[$name] = $parametres;
        $event = array(
            "name" => $name,
            "parameters" => $parametres,
        );

        if(!$this->isSocket())
        {
            $this->events[] = $event;
        }
        else
        {
            if(empty($clients))
            {
                $user = $this->getUser();

                if(!is_object($user)) {
                    return;
                }

                $clients = array($user->getLastSessionId());
            }

            $event["clients"] = $clients;

            $this->getSocket()->send(
                Elephant::TYPE_EVENT,
                null,
                null,
                json_encode(array(
                        "name" => "proxyevent",
                        "args" => $event,
                ))
            );
        }
    }
    
    public function getAllEvents()
    {
        return $this->events;
    }
    
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();

        if(!$this->isSocket())
        {
            $data = json_encode($this->getAllEvents());

            if(strlen($data) > 10000) throw new \Exception("Data event client header too long");

            $response->headers->set("app-event-client", $data);
        }
    
        foreach($this->headers as $name => $value)
        {
            $response->headers->set($name, $value);
        }
    }
    
}

?>
