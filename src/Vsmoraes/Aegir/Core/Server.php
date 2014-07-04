<?php namespace Vsmoraes\Aegir\Core;

class Server {
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
            socket_close($client);
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

            $this->sendWelcome($socket_new);

            $this->clients[] = $socket_new; // add the new client to the client's array

            unset($this->changed[0]); // remove the server socket from the changed

            socket_getpeername($socket_new, $ip);
            $this->debug('Client', 'Client connected from: ' . $ip);
        }

        return $this;
    }

    /**
     * Send a welcome message to the new clients
     * @param resource $socket client socket
     *
     * @return void
     */
    protected function sendWelcome($socket)
    {
        $msg = "\nAegir simple server.\n\n";

        $this->sendMessage($socket, $msg);
    }

    /**
     * Check if someone disconnected
     *
     * @return SocketServer this
     */
    protected function checkDisconnect()
    {
        foreach ( $this->changed as $changed_socket ) {
            $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
            if ( $buf !== false || count($this->clients) < 1 ) { // check disconnected client
                continue;
            }

            // remove client for $clients array
            $found_socket = array_search($changed_socket, $this->clients);
            unset($this->clients[$found_socket]);

            socket_getpeername($changed_socket, $ip);
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
        foreach ($this->changed as $key => $socket) {
            $buffer = null;
            while ( socket_recv($socket, $buffer, 1024, 0) >= 1 ) {
                if ( trim($buffer) == '' ) {
                    continue;
                }

                $buffer = trim($buffer) . PHP_EOL;
                $this->parseCommand($socket, $buffer);
                /*
                $this->sendMessage($socket, '>> Echo: ' . trim($buffer) . PHP_EOL);
                */
                socket_getpeername($socket, $ip);
                $this->debug('Client', 'Message from ' . $ip . ': ' . trim($buffer));

                unset($this->changed[$key]);
                break;
            }
        }
    }
    
    /**
     * Resets all changed sockets and wait for interactions
     * @return [type] [description]
     *
     * @return SocketServer this
     */
    protected function waitForChange()
    {
        // reset changed
        $this->changed = array_merge([$this->socket], $this->clients);

        // variable call time pass by reference req of socket_select
        $null = null;

        // this next part is blocking so that we dont run away with cpu
        socket_select($this->changed, $null, $null, null);

        return $this;
    }
    
    /**
     * Send a message to the given socket
     * @param  resource $client client socket
     * @param  string $msg message
     * 
     * @return true
     */
    public function sendMessage($client, $msg)
    {
        $msg .= "\n\r";
        socket_write($client, $msg, strlen($msg));
        
        return true;
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
}