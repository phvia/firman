<?php
/**
 * Firman package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include 'loader.php';

$socket = 'tcp://0.0.0.0:8080';

$server = new \Firman\Server();

$server
    // Parameter.
    //
    // option, default is 1
    ->setCount(2)
    // option, can also be in constructor
    ->setSocket($socket)
    // option, default is Firman
    //->setProcessTitle('Firman')
    // option, default is /tmp
    ->setPpidPath('/tmp')
    // option, default is 100
    ->setBacklog(100)
    // option, default is 200
    ->setSelectTimeout(100)
    // option, default is 60
    ->setAcceptTimeout(0)

    // Event callback.
    //
    // option, when client connected with server, callback trigger.
    ->onConnection(function() {
        echo "Server say: new client connected.\n";
    })
    // option, when client send message to server, callback trigger.
    ->onMessage(function($connection, $received_string) {

        // Echo client data.
        echo sprintf("Server say, data from client is '%s'\n", $received_string);

        sleep(2);

        // Send server data.
        $string = date('Y-m-d H:i:s');
        $connection->send($string);
    })

    // Run server.
    //
    ->run();
