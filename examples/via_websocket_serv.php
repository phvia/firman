<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include __DIR__ . '/../../../autoload.php';

$socket = 'tcp://0.0.0.0:8080';

$server = new \Via\Server();

$has_hand_shake = false;

$server
    // Parameter.
    //
    // option, default is 1
    ->setCount(2)
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
        //echo "Server: New client connected." . PHP_EOL;
    })
    // option, when client send message to server, callback trigger.
    ->onMessage(function($connection) use ($has_hand_shake) {

//        $connection->recv();
//        $connection->send();
//        $connection->close();

        $custom_response_header = [
            'Server' => 'Via custom',
        ];

//        while (true) {

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

//            GET / HTTP/1.1
//            Host: 10.0.2.15:8080
//            User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:59.0) Gecko/20100101 Firefox/59.0
//            Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
//            Accept-Language: en-US,en;q=0.5
//            Accept-Encoding: gzip, deflate
//            Sec-WebSocket-Version: 13
//            Origin: http://10.0.2.15
//            Sec-WebSocket-Extensions: permessage-deflate
//            Sec-WebSocket-Key: esmj9cRPJ0gtimdLGKQPkA==
//            Connection: keep-alive, Upgrade
//            Pragma: no-cache
//            Cache-Control: no-cache
//            Upgrade: websocket

//                echo "=======================begin====================\n";
//                print_r($buffer);
//                echo "=======================end======================\n";

                // Http protocol: https://en.wikipedia.org/wiki/Hypertext_Transfer_Protocol#Overview
                // Http request format check.
                if (false !== $buffer) {

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

                            if (!empty($line)) {

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
                    // Append custom header
                    foreach ($custom_response_header as $k => $v) {
                        $response_header .= "{$k}: {$v}\r\n";
                    }
                }
                $response_header .= "\r\n";

                if (false !== fwrite($connection, $response_header, strlen($response_header))) {
//                    echo "Hand Shake Success\n";
                    $has_hand_shake = true;
                }
            }

            // TODO: fix decode to confusion code
            // Receive from client.

            // Read client message from connection.
            // Base Data Framing Protocol: https://tools.ietf.org/html/rfc6455#page-28

            if ($buffer = fread($connection, 8192)) {

                $len = $masks = $data = $decoded = null;
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
                    $decoded .= $data[$index] ^ $masks[$index % 4];
                }
//                echo "Recv from client: {$decoded}\n";
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

            // TODO: 粘包？

            // errno=32 Broken pipe: http://www.php.net/manual/fr/function.fwrite.php#96951
            // sleep 1 before fwrite.

            sleep(1);

            // TODO: If client browser close or refresh, child will block on recvfrom, later will cause SIGPIPE problem, may close this client connection.
            // TODO: the SIGPIPE signal default action is kill current process. so Via reload a new one.
            // TODO: we can set SIG_IGN in installChildSignal, but it can not deal with any connection, recvfrom noting.
            //            recvfrom(7, "", 8192, 0, NULL, NULL)    = 0
            //            nanosleep({1, 0}, 0x7ffdfe49c870)       = 0
            //            sendto(7, "\201\0362018-03-28 18:43:56+1973752716", 32, 0, NULL, 0) = 32
            //            write(1, "Server: write to client success\n", 32) = 32
            //            recvfrom(7, "", 8192, 0, NULL, NULL)    = 0
            // SO_KEEPALIVE affect SIGPIPE signal.

            // Other: Write twice to a closed socket also cause SIGPIPE.
            $length = @fwrite($connection, $ns, 8192);

            if ((false !== $length) && ($length === strlen($ns))) {
//                echo "Server: write to client success" . PHP_EOL;
            }
//        }

        // TODO： Why connection closed by itself.
    })

    // Run server.
    //
    ->run();
