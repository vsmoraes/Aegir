<?php namespace Vsmoraes\Aegir;

use Vsmoraes\Aegir\Core\Server;

class Aegir extends Server {

    public function __construct($host, $port, $debug)
    {
        parent::__construct($host, $port, $debug);

        $this->registerEvents();

        return $this;
    }

    protected function registerEvents()
    {
        $this->bindLogin();
        $this->bindMessageToAll();
        $this->bindDirectMessage();
    }

    private function bindLogin()
    {
        $this->on('login', function($event, $e, $client) {
        });
    }

    private function bindMessageToAll()
    {
        $this->on('message', function($event, $e, $client) {
            $client_list = $this->getClients();

            foreach ( $client_list AS $c ) {
                $msg = [
                    'event'   => 'message',
                    'message' => $e['message']
                ];
                $c->sendMessage($msg);
            }
        });
    }

    private function bindDirectMessage()
    {
        $this->on('direct-message', function($event, $e, $client) {
            $target = $this->getClient($e['target']);
            if ( $target ) {
                $msg = [
                    'event'   => 'direct-message',
                    'source'  => $client->getId(),
                    'message' => $e['message']
                ];
                $target->sendMessage($msg);
            }
        });
    }

}