<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include __DIR__ . '/../../../vendor/autoload.php';
include __DIR__ . '/../Server.php';

$socket = 'tcp://0.0.0.0:8080';

$con = new \Via\Server();

$con
    // Parameter.
    //
    // option, default is 1
    ->setCount(3)
    // option, can also be in constructor
    ->setSocket($socket)
    // option, default is Via
    ->setProcessTitle('Via')
    // option, default is 100
    ->setBacklog(100)
    // option, default is 30
    ->setSelectTimeout(20)
    // option, default is 60
    ->setAcceptTimeout(30)

    // Event callback.
    //
    // option, when client connected with server, callback trigger.
    ->onConnection(function($connection) {
        echo "New client connected." . PHP_EOL;
    })
    // option, when client send message to server, callback trigger.if
    ->onMessage(function($connection) {
        $method             = '';
        $url                = '';
        $protocol_version   = '';

        $request_header     = [];
        $content_type       = 'text/html; charset=utf-8';
        $content_length     = 0;
        $request_body       = '';
        $end_of_header      = false;

        // @see http://php.net/manual/en/function.fread.php
        $buffer = fread($connection, 8192);

        if (false !== $buffer) {

            // Http request format check.
            if (false !== strstr($buffer, "\r\n")) {
                $list = explode("\r\n", $buffer);
            }

            if ($list) {
                foreach ($list as $line) {
                    if ($end_of_header) {
                        if (strlen($line) === $content_length) {
                            $request_body = $line;
                        } else {
                            throw new \Exception("Content-Length {$content_length} not match request body length " . strlen($line) . "\n");
                        }
                        break;
                    }

                    if ( empty($line) ) {
                        $end_of_header = true;
                    } else {
                        // Header.
                        //
                        if (false === strstr($line, ': ')) {
                            $array = explode(' ', $line);

                            // Request line.
                            if (count($array) === 3) {
                                $method           = $array[0];
                                $url              = $array[1];
                                $protocol_version = $array[2];
                            }
                        } else {
                            $array = explode(': ', $line);

                            // Request header.
                            list ($key, $value) = $array;
                            $request_header[$key] = $value;

                            if ( strtolower($key) === strtolower('Content-type') ) {
                                $content_type = $value;
                            }

                            // Have request body.
                            if ($key === 'Content-Length') {
                                $content_length = $value;
                            }
                        }
                    }
                }
            }
        }

        // No request body, show buffer from read.
        $response_body = $request_body ?: $buffer;
        $response_header = "HTTP/1.1 200 OK\r\n";
        $response_header .= "Content-type: {$content_type}\r\n";
        if (empty($content_length) && (strlen($response_body) > 0)) {
            $response_header .= "Content-Length: " . strlen($response_body) . "\r\n";
        }
        foreach ($request_header as $k => $v) {
            $response_header .= "{$k}: {$v}\r\n";
        }
        $response_header .= "\r\n";
        fwrite($connection, $response_header . $response_body);
        fclose($connection);
    })

    // Start server.
    //
    ->run();

