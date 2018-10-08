<?php
/**
 * Firman package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

namespace Firman;

use Firman\Protocol\WebSocket;

/**
 * Class Connection
 *
 * @package Firman
 */
class Connection
{
    protected $socket_connection;

    /**
     * Connection constructor.
     *
     * @param Resource $socket_connection
     */
    public function __construct($socket_connection)
    {
        $this->socket_connection = $socket_connection;
    }

    /**
     * Send encoded data to client.
     *
     * @param string $string
     *
     * @return bool|int
     */
    public function send($string)
    {
        $encoded_string = WebSocket::encode($string);

        $length = @fwrite($this->socket_connection, $encoded_string, 8192);

        return $length;
    }
}
