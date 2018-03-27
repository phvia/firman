# via

PHP multi-process and non-blocking I/O library.

## Usage shown

```php
# Simplest configure

(new \Via\Server('tcp://0.0.0.0:8080'))->run();
```

```php
# All configure

$socket = 'tcp://0.0.0.0:8080';

$server = new \Via\Server();

$server
    // Parameter.
    
    // optional, default is 1
    // Set child process number
    ->setCount(1)
    
    // optional, can also be in constructor
    // Set socket
    ->setSocket($socket)
    
    // optional, default is Via
    // Set process title
    ->setProcessTitle('Via')
    
    // optional, default is /tmp
    // Set the path of file saved ppid
    ->setPpidPath('/tmp')
    
    // optional, default is 100
    // Set socket backlog number
    ->setBacklog(100)
    
    // optional, default is 30
    // Set select system call timeout value
    ->setSelectTimeout(5)
    
    // optional, default is 60
    // Set accept timeout value
    ->setAcceptTimeout(10)

    // Event callback.
    
    // optional, when client connected with server, callback trigger.
    // Set connection event callback task
    ->onConnection(function($connection) {
        echo "New client connected." . PHP_EOL;
    })
    
    // optional, when client send message to server, callback trigger.
    // Set message event callback task
    ->onMessage(function($connection) {
        // implement your logic
    })
    
    // Run server.
    
    ->run();
```

## Run example:  
```shell
# Startup

$ composer require phpvia/via
$ composer install
$ php /path/to/examples/via_websocket_serv.php -h

# Process control command

$ php xxx.php start
$ php xxx.php restart
$ php xxx.php stop
```

## Contribute:  
Coding Standards: https://symfony.com/doc/current/contributing/code/standards.html

## Other resource:  
Composer document: https://getcomposer.org/doc/  
Symfony Console Component: http://symfony.com/doc/current/components/console.html

## Group
QQ group: 377154148

## Todo
Implement protocol parse built-in.

## License
MIT

