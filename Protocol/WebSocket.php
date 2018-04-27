<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

namespace Via\Protocol;

/**
 * Class WebSocket
 *
 * @package Via\Protocol
 */
class WebSocket
{
    /**
     * Has handshake or not.
     *
     * @var bool
     */
    protected static $has_handshake = false;

    /**
     * Custom append HTTP response header.
     *
     * Example: ['Server' => 'Via super']
     *
     * @var array
     */
    protected static $custom_response_headers = [];

    /**
     * Buffer data from read.
     *
     * @var string
     */
    protected static $read_buffer = '';

    /**
     * Deal with TCP handshake.
     *
     * Handshake: https://en.wikipedia.org/wiki/WebSocket#Protocol_handshake
     * RFC 6455: https://tools.ietf.org/html/rfc6455
     *
     * @param Resource $socket_connection
     *
     * @return bool handshake true on success or false on failure
     */
    public static function doHandshake($socket_connection)
    {
        if (! is_resource($socket_connection) || (! $request_headers = static::parseHttpHeader($socket_connection)) ) {
            return false;
        }

        if (! static::$has_handshake) {
            // Do handshake response.
            $response_header = "HTTP/1.1 101 Switching Protocols\r\n";
            $response_header .= "Upgrade: websocket\r\n";
            $response_header .= "Connection: Upgrade\r\n";
            $response_header .= sprintf("Sec-WebSocket-Accept: %s\r\n", base64_encode(sha1($request_headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)));

            if (static::$custom_response_headers) {
                foreach (static::$custom_response_headers as $k => $v) {
                    $response_header .= sprintf("%s: %s\r\n", $k, $v);
                }
            }

            $response_header .= "\r\n";

            if (false !== fwrite($socket_connection, $response_header, strlen($response_header))) {
                static::$has_handshake = true;
            }
        }

        return true;
    }

    /**
     * Read and decode received data.
     *
     * @param Resource $socket_connection
     *
     * @return null|string
     */
    public static function decode($socket_connection): string
    {
        if (! is_resource($socket_connection)) {
            return '';
        }

        $decoded_string = '';

        if ($buffer = fread($socket_connection, 8192)) {
            static::$read_buffer = $buffer;

            $len = $masks = $data = $decoded_data = null;

            $len = ord($buffer[1]) & 127;

            if ($len === 126) {
                $masks = substr($buffer, 4, 4);
                $data = substr($buffer, 8);

            } else if ($len === 127) {
                $masks = substr($buffer, 10, 4);
                $data = substr($buffer, 14);
            } else {
                $masks = substr($buffer, 2, 4);
                $data = substr($buffer, 6);
            }

            for ($index = 0; $index < strlen($data); $index++) {
                $decoded_string .= $data[$index] ^ $masks[$index % 4];
            }
        }

        return $decoded_string;
    }

    /**
     * Encode data that will send.
     *
     * @param $string
     *
     * @return string
     */
    public static function encode($string): string
    {
        $encoded_string = '';

        $data = str_split($string, 125);

        if (count($data) == 1) {
            $encoded_string = "\x81" . chr(strlen($data[0])) . $data[0];
        } else {
            foreach ($data as $o) {
                $encoded_string .= "\x81" . chr(strlen($o)) . $o;
            }
        }

        return $encoded_string;
    }

    /**
     * Parse HTTP protocol.
     *
     * Http protocol: https://en.wikipedia.org/wiki/Hypertext_Transfer_Protocol#Overview
     * Article: http://www.cnblogs.com/farwish/p/8418969.html
     *
     * @param Resource $socket_connection
     *
     * @return array request headers
     *
     * @throws Exception
     */
    protected static function parseHttpHeader($socket_connection): array
    {
        if (! is_resource($socket_connection)) {
            return [];
        }

        $method             = '';
        $url                = '';
        $protocol_version   = '';

        $request_headers        = [];
        $content_type           = 'text/html; charset=utf-8';
        $content_length         = 0;
        $end_of_request_header  = false;
        $request_body           = '';

        // Default is also 8192.
        $buffer = fread($socket_connection, 8192);

        if (false !== $buffer) {

            $lines = [];
            if (false !== strstr($buffer, "\r\n")) {
                $lines = explode("\r\n", $buffer);
            }

            if ($lines) {
                foreach ($lines as $line) {

                    if ($end_of_request_header) {

                        // If end of request header, check body length is matched Content-Length.
                        if (strlen($line) == $content_length) {
                            // Get body content and parse over.
                            $request_body = $line;
                            break;
                        }
                    }

                    if (! empty($line)) {

                        // Request line.
                        // HTTP/1.1 200 OK
                        if (false === strstr($line, ': ')) {
                            $array = explode(' ', $line);

                            if (count($array) === 3) {
                                $method             = $array[0];
                                $url                = $array[1];
                                $protocol_version   = $array[2];
                            }
                        } else {
                            // Request header.
                            // Date: Fri, 27 Apr 2018 02:47:46 GMT
                            $array = explode(': ', $line);

                            list ($key, $value) = $array;

                            $request_headers[$key] = $value;

                            if (strtolower($key) === 'content-type') {
                                $content_type = $value;
                            }

                            if (strtolower($key) === 'content-length') {
                                $content_length = $value;
                            }
                        }
                    } else {
                        // End of request header, followed by a blank line.
                        // https://en.wikipedia.org/wiki/Hypertext_Transfer_Protocol#Server_response
                        $end_of_request_header = true;
                    }
                }
            }
        }

        return $request_headers;
    }
}