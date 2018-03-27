# via

PHP multi-process and non-blocking I/O library.

## Usage shown
```php
$server = new \Via\Server();

$server
    // Parameter.
    
    // optional, default is 1
    ->setCount(1)
    
    // optional, can also be in constructor
    ->setSocket($socket)
    
    // optional, default is Via
    ->setProcessTitle('Via')
    
    // optional, default is /tmp
    ->setPpidPath('/tmp')
    
    // optional, default is 100
    ->setBacklog(100)
    
    // optional, default is 30
    ->setSelectTimeout(5)
    
    // optional, default is 60
    ->setAcceptTimeout(10)

    // Event callback.
    
    // optional, when client connected with server, callback trigger.
    ->onConnection(function($connection) {
        echo "New client connected." . PHP_EOL;
    })
    
    // optional, when client send message to server, callback trigger.
    ->onMessage(function($connection) {
        // implement your logic
    })
    
    // Run server.
    
    ->run();
```

## Run example:  
`$ composer install`  
`$ php examples/via_websocket_serv.php`  

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

