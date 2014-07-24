<?php namespace Vsmoraes\Aegir;

use Vsmoraes\Aegir\Core\Server;

class Aegir extends Server {

    public function __construct($host, $port, $debug)
    {
        parent::__construct($host, $port, $debug);

        $this->registerEvents();

        return $this;
    }

    public function updateUserList()
    {
        $clients = $this->getClients();

        $client_name_list = [];

        foreach ( $clients AS $client ) {
            if ( $client->isAuthenticated() ) {
                $client_name_list[] = $client->getUsername();
            }
        }

        $this->broadcast([
            'event'     => 'updateUserList',
            'user_list' => $client_name_list
        ]);
    }

    public function broadcast($message)
    {
        $clients = $this->getClients();

        foreach ( $clients AS $client ) {
            if ( $client->isAuthenticated() ) {
                $client->sendMessage($message);
            }
        }
    }

    protected function registerEvents()
    {
        $this->bindDisconnect();
        $this->bindLogin();
        $this->bindMessageToAll();
        $this->bindDirectMessage();
    }

    private function bindDisconnect()
    {
        $this->on('disconnect', function($event, $e, $client) {
            $this->broadcast([
                'event' => 'message',
                'message' => $client->getUsername() . ' has left the room'
            ]);
            $this->updateUserList();
        });
    }

    private function bindLogin()
    {
        $this->on('login', function($event, $e, $client) {
            $client->setUsername($e['username']);

            $this->broadcast([
                'event'   => 'message',
                'message' => $e['username'] . ' has joined the room'
            ]);
            $this->updateUserList();
        });
    }

    private function bindMessageToAll()
    {
        $this->on('message', function($event, $e, $client) {
            $message = $client->getUsername() . ': ' . $e['message'];
            $this->broadcast([
                'event'   => 'message',
                'message' => $message
            ]);
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