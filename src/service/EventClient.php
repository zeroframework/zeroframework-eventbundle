<?php

namespace service;

use ElephantIO\Client as Elephant;
use Exception\SocketIoConnectionFail;

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

    private $configuration = array();
    
    public function __construct($logger, $container)
    {
        $this->logger = $logger;

        $this->socket = null;

        $this->configuration = array_merge(array(
            "url" => "http://localhost:8765",
            "autoboot" => true,
            "path" => "socket.io",
            "protocol" => 1,
            "read" => false,
            "checkSslPeer" => true,
            "debug" => true
        ), $container->get("elephant.io.config"));

        if(!$this->isSocket() && $this->configuration["autoboot"] === true)
        {
            $this->connect();
        }
    }

    public function connect()
    {
        try {
            $this->socket = new Elephant(
                $this->configuration["url"],
                $this->configuration["path"],
                $this->configuration["protocol"],
                $this->configuration["read"],
                $this->configuration["checkSslPeer"],
                $this->configuration["debug"]
            );

            $this->getSocket()->init();
        }
        catch(\Exception $e)
        {
            throw new SocketIoConnectionFail("Server eventlive driven not started 'nohup php app/console websocket::command'");
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

    public function getSessionsByUsers(array $users = array())
    {
        $receivers = array();

        foreach($users as $userEyes)
        {
            $receivers[] = $userEyes->getLastSessionId();
        }

        return $receivers;
    }

    public function getSessionByProfiles(array $profiles = array())
    {
        $receivers = array();

        foreach($profiles as $userEyes)
        {
            $receivers[] = $userEyes->getLastSessionId();
        }

        return $receivers;
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
                return;
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
}

?>
