<?php namespace Vsmoraes\Aegir\Core;

use Vsmoraes\Aegir\Core\MessageHandler;

class Client extends MessageHandler {
    private $server_confs = [];

    protected $socket;
    protected $id;
    protected $auth = false;
    protected $username = '';

    public function setUsername($username)
    {
        $this->username = $username;
        $this->auth = true;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function isAuthenticated()
    {
        return $this->auth;
    }

    public function __construct($socket, Server $server)
    {
        $this->socket = $socket;

        $this->server_confs = [
            'host' => $server->getHost(),
            'port' => $server->getPort()
        ];
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function disconnect()
    {
        $this->auth = false;
        socket_close($this->socket);
    }

    public function getIp()
    {
        socket_getpeername($this->socket, $ip);
        return $ip;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Send a message to the given socket
     * @param  resource $client client socket
     * @param  mixed $msg message params
     * @param  boolean $plain Params is text or array
     * 
     * @return true
     */
    public function sendMessage($msg = array(), $plain = false)
    {

        if ( ! $plain ) {
            $msg = $this->mask(json_encode($msg) . "\r\n");
        } else {
            $msg .= "\r\n";
        }

        socket_write($this->socket, $msg, strlen($msg));
        
        return true;
    }

    public function sendHandshake($message)
    {
        $tmp = explode("\r\n", $message);
        $parms = [];
        foreach ( $tmp AS $t ) {
            if ( preg_match ('/: /', $t) ) {
                list($key, $value) = explode(':', $t);

                $parms[trim($key)] = trim($value);
            }
        }

        if ( array_key_exists('Sec-WebSocket-Key', $parms) ) {
            $secKey = $parms['Sec-WebSocket-Key'];
            $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: {$this->server_confs['host']}\r\n" .
            "WebSocket-Location: ws://{$this->server_confs['host']}:{$this->server_confs['port']}\r\n".
            "Sec-WebSocket-Accept:{$secAccept}\r\n";

            $this->sendMessage($upgrade, true);
        }
    }
}