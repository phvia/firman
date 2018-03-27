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

$server = new \Via\Server();

$has_hand_shake = false;

$server
    // Parameter.
    //
    // option, default is 1
    ->setCount(1)
    // option, can also be in constructor
    ->setSocket($socket)
    // option, default is Via
    ->setProcessTitle('Via')
    // option, default is /tmp
    ->setPpidPath('/tmp')
    // option, default is 100
    ->setBacklog(100)
    // option, default is 30
    ->setSelectTimeout(5)
    // option, default is 60
    ->setAcceptTimeout(10)

    // Event callback.
    //
    // option, when client connected with server, callback trigger.
    ->onConnection(function($connection) {
        echo "New client connected." . PHP_EOL;
    })
    // option, when client send message to server, callback trigger.
    ->onMessage(function($connection) use (&$has_hand_shake) {

//        $connection->recv();
//        $connection->send();
//        $connection->close();

        $custom_response_header = [
            'Server' => 'Via',
        ];


        // Parse Http Header and Hand Shake.
        if (! $has_hand_shake) {

            // Parse http header.
            // www.cnblogs.com/farwish/p/8418969.html

            $method = '';
            $url = '';
            $protocol_version = '';

            $request_header = [];
            $content_type = 'text/html; charset=utf-8';
            $content_length = 0;
            $request_body = '';
            $end_of_header = false;

            $buffer = fread($connection, 8192);

            print_r($buffer);

            if (false !== $buffer) {

                // Http protocol: https://en.wikipedia.org/wiki/Hypertext_Transfer_Protocol#Overview
                // Http request format check.

                if (false !== strstr($buffer, "\r\n")) {
                    $list = explode("\r\n", $buffer);
                }

                if ($list) {
                    foreach ($list as $line) {

                        if ($end_of_header) {

                            // Check body length is match Content-Length.

                            if (strlen($line) === $content_length) {
                                $request_body = $line;
                                break;
                            } else {
                                throw new \Exception("Content-Length {$content_length} not match request body length " . strlen($line) . "\n");
                            }
                        }

                        if (! empty($line)) {

                            if (false === strstr($line, ': ')) {
                                $array = explode(' ', $line);

                                // Request line.

                                if (count($array) === 3) {
                                    $method = $array[0];
                                    $url = $array[1];
                                    $protocol_version = $array[2];
                                }
                            } else {

                                // Request header.

                                $array = explode(': ', $line);

                                list ($key, $value) = $array;
                                $request_header[$key] = $value;

                                if (strtolower($key) === strtolower('Content-type')) {
                                    $content_type = $value;
                                }

                                // Have request body.

                                if (strtolower($key) === strtolower('Content-Length')) {
                                    $content_length = $value;
                                }
                            }
                        } else {
                            $end_of_header = true;
                        }
                    }
                }
            }

            // Handshake: https://en.wikipedia.org/wiki/WebSocket#Protocol_handshake
            // RFC 6455: https://tools.ietf.org/html/rfc6455
            // Do handshake, response.
            $response_header = "HTTP/1.1 101 Switching Protocols\r\n";
            $response_header .= "Upgrade: websocket\r\n";
            $response_header .= "Connection: Upgrade\r\n";
            $response_header .= "Sec-WebSocket-Accept: " .
                                base64_encode(sha1($request_header['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)) . "\r\n";
            if ($custom_response_header) {
                foreach ($custom_response_header as $k => $v) {
                    $response_header .= "{$k}: {$v}\r\n";
                }
            }
            $response_header .= "\r\n";

            if (false !== fwrite($connection, $response_header, strlen($response_header))) {
                echo "Hand Shake Success\n";
                $has_hand_shake = true;
            }
        }

        // Recv from client.
        // Read client message from connection.
        // Base Data Framing Protocol: https://tools.ietf.org/html/rfc6455#page-28

        if ( $buffer = fread($connection, 8192) ) {

            $len = $masks = $data = $decoded = null;
            $len = ord($buffer[1]) & 127;
            if ($len === 126) {
                $masks = substr($buffer, 4, 4);
                $data = substr($buffer, 8);

            }
            else if ($len === 127) {
                $masks = substr($buffer, 10, 4);
                $data = substr($buffer, 14);
            }
            else {
                $masks = substr($buffer, 2, 4);
                $data = substr($buffer, 6);
            }
            //
            for ($index = 0; $index < strlen($data); $index++) {
                $decoded .= $data[$index] ^ $masks[$index % 4];
            }
            echo "Recv from client: {$decoded}\n";
        }

        // Write into socket.

        $s = date('Y-m-d H:i:s') . '+' . rand();

        $a = str_split($s, 125);

        //添加头文件信息，不然前台无法接受
        if (count($a) == 1) {
            $ns = "\x81" . chr(strlen($a[0])) . $a[0];
        } else {
            $ns = "";
            foreach ($a as $o) {
                $ns .= "\x81" . chr(strlen($o)) . $o;
            }
        }

        // errno=32 Broken pipe: http://www.php.net/manual/fr/function.fwrite.php#96951
        // sleep 1 before fwrite.

        // If client browser refresh, will cause SIGPIPE problem and strace will see infinite loop!
        $length = @fwrite($connection, $ns, 8192);

        if ((false !== $length) && ($length === strlen($ns))) {
            echo 'Write to client success' . PHP_EOL;
        }

        fclose($connection);

        exit();
    })

    // Run server.
    //
    ->run();

