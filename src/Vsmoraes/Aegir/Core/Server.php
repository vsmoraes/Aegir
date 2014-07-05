<?php namespace Vsmoraes\Aegir\Core;

use Vsmoraes\Aegir\Core\MessageHandler;
use Vsmoraes\Aegir\Core\Client;

class Server extends MessageHandler {
    /**
     * Server host
     * @var string
     */
    protected $host;

    /**
     * Server port
     * @var integer
     */
    protected $port;

    /**
     * Server socket
     * @var resource
     */
    protected $socket;

    /**
     * Client connections
     * @var array
     */
    protected $clients = [];

    /**
     * Sockets with iterations
     * @var array
     */
    protected $changed;

    protected $listeners = [];

    /**
     * Open the server socket and authenticate with the Asterisk client
     * @param string         $host     server host
     * @param integer        $port     server port
     * @param boolean        $debug    debug
     *
     * @return SocketServer this
     */
    public function __construct($host = 'localhost', $port = 10000, $debug = false)
    {
        $this->host = $host;
        $this->port = $port;

        $this->debug = $debug;

        // Create the initial socket
        $this->openSocket();

        // Chain
        return $this;
    }
    
    /**
     * Class destructor
     * Disconnect all connected clients when the script stops;
     * Close the server socket;
     *
     * @return void
     */
    public function __destruct()
    {
        foreach( $this->clients AS $client ) {
            $client->disconnect();
        }

        socket_close($this->socket);
    }

    /**
     * Display logs on the server console
     * @param  string $from from where it comes
     * @param  string $str  log message
     * 
     * @return void
     */
    protected function debug($from, $str)
    {
        if ( $this->debug ) {
            printf("[%s]: %s\n", $from, $str);
        }
    }

     /**
     * Initiate the server socket and start listening
     * 
     * @return void
     */
    protected function openSocket()
    {
        $this->debug('Server', 'Opening socket');

        // make script run indefinitely
        set_time_limit(0);

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        socket_bind($this->socket, 0, $this->port);

        socket_listen($this->socket);
    }

    /**
     * Check if there are new clients and handle it
     *
     * @return SocketServer this
     */
    protected function checkNewClients()
    {
        // Check interations with the server socket
        if ( in_array($this->socket, $this->changed) ) {
            $socket_new = socket_accept($this->socket); //accept new socket

            $client_id = $this->generateClientId();

            $this->clients[$client_id] = new Client($socket_new, $this); // add the new client to the client's array
            $this->clients[$client_id]->setId($client_id);

            unset($this->changed[0]); // remove the server socket from the changed

            $this->debug('Client', 'Client connected from: ' . $this->clients[$client_id]->getIp());
        }

        return $this;
    }

    protected  function generateClientId()
    {
        $client_count = count($this->clients);
        $key = preg_replace_callback('/([ ])/', function() { return chr(rand(97,122)); }, '     ');

        return md5($key . $client_count);
    }

    /**
     * Check if someone disconnected
     *
     * @return SocketServer this
     */
    protected function checkDisconnect()
    {
        foreach ( $this->changed AS $id => $changed_socket ) {

            $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);

            if ( $buf !== false || $id == '_server' || count($this->clients) < 1 ) { // check disconnected client
                continue;
            }

            // remove client for $clients array
            //$found_socket = array_search($changed_socket, $this->clients);
            //unset($this->clients[$found_socket]);
            $ip = $this->clients[$id]->getIp();
            $this->clients[$id]->disconnect();

            unset($this->clients[$id]);

            $this->debug('Client', 'Client disconnected: ' . $ip);
        }

        return $this;
    }

    /**
     * Main loop of the server process
     * 
     * @return void
     */
    public function run()
    {
        $this->debug('Server', 'Running');

        while ( true ) {
            $this->waitForChange();
            $this->checkNewClients();
            $this->checkMessageRecieved();
            $this->checkDisconnect();
        }
    }
    
    /**
     * Check if there are new messages on the sockets and handle it
     *
     * @return void
     */
    public function checkMessageRecieved()
    {
        foreach ( $this->changed AS $key => $socket ) {
            $buffer = null;
            while ( @socket_recv($socket, $buffer, 1024, 0) >= 1 ) {
                if ( trim($buffer) == '' ) {
                    continue;
                }

                $buffer = trim($buffer) . PHP_EOL;

                $this->handleRecievedMessage($this->clients[$key], $buffer);

                unset($this->changed[$key]);
                break;
            }
        }
    }
    
    /**
     * Resets all changed sockets and wait for interactions
     *
     * @return SocketServer this
     */
    protected function waitForChange()
    {
        // reset changed
        $this->changed = []; //array_merge([$this->socket], $this->clients);
        $this->changed['_server'] = $this->socket;
        foreach ( $this->clients AS $id => $client ) {
            $this->changed[$id] = $client->getSocket();
        }

        // variable call time pass by reference req of socket_select
        $null = null;

        // this next part is blocking so that we dont run away with cpu
        socket_select($this->changed, $null, $null, null);

        return $this;
    }

    /**
     * Retrieve the client sockets
     * 
     * @return array client sockets
     */
    public function getClients()
    {
        return $this->clients;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getClient($id)
    {
        if ( array_key_exists($id, $this->clients) ) {
            return $this->clients[$id];
        } else {
            return null;
        }
    }

    public function handleRecievedMessage($client, $message)
    {
        $json = $this->unmask(trim($message));
        $json = json_decode($json, true);
        
        if ( ! is_array($json) ) { // Probably the handshake
            $client->sendHandshake($message);
        } else {
            if ( array_key_exists('event', $json) ) {
                $this->fireEvent($json['event'], $client, $json);
            } else {
                // do nothing...
            }
        }
    }

    protected function fireEvent($event, $client, $params)
    {
        if ( array_key_exists($event, $this->listeners) ) {
            $this->listeners[$event]($event, $params, $client);
        } elseif ( array_key_exists('*', $this->listeners) ) {
            $this->listeners['*']($event, $params, $client);
        }
    }

    public function on($event, $closure)
    {
        $this->listeners[$event] = $closure;
    }
}