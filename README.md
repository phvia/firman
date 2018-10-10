# Firman

PHP multi-process and non-blocking I/O library.

## Dependency
* PHP>=7. (for new features and high performance)
* PCNTL extension. (compile PHP with `--enable-pcntl` option to enable)
* Sockets extension. (compile PHP with `--enable-sockets` option to enable)

## Install
```shell
$ composer require phvia/firman:dev-master
```

## Run example

* Show usage  
```shell
$ php /path/to/firman/examples/via_websocket_serv_builtin.php

Firman package 0.0.1

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help     Displays help for a command
  list     Lists commands
  restart  Restart Firman server
  start    Start Firman server
  stop     Stop Firman server
```

* Show usage detail
```shell
$ php /path/to/firman/examples/via_websocket_serv_builtin.php start -h

Usage:
  start [options]

Options:
  -e, --env=ENV         The Environment name (support: dev, prod) [default: "dev"]
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Start Firman server
```

* Process control
```shell
# After start server, you can access examples/ws.html in web browser
$ php examples/via_websocket_serv_builtin.php start

# Restart Firman server
$ php examples/via_websocket_serv_builtin.php restart

# Stop Firman server
$ php examples/via_websocket_serv_builtin.php stop
```

* Run in daemon
```shell
$ php /path/to/firman/xxx.php start --env=prod
# OR
$ php /path/to/firman/xxx.php start --eprod
$
$ ps auxf | grep Firman
```

## Do it yourself

* Simplest configure
```php
include '/path/to/vendor/autoload.php';

(new \Firman\Server('tcp://0.0.0.0:8080'))->run();
```

* Full configure
```php
inlcude '/path/to/vendor/autoload.php';

$server = new \Firman\Server();

$socket = 'tcp://0.0.0.0:8080';

$server
    // Parameter.
    
    // optional, default is 1
    // Set child process number
    ->setCount(1)
    
    // optional, can also be in constructor
    // Set socket
    ->setSocket($socket)
    
    // optional, default is Firman
    // Set process title
    ->setProcessTitle('Firman')
    
    // optional, default is /tmp
    // Set the path of file saved ppid
    ->setPpidPath('/tmp')
    
    // optional, default is 100
    // Set socket backlog number
    ->setBacklog(100)
    
    // optional, default is 200
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

## Server workflow explain

* Check environment.  
* Parse command.  
* Initialize master process information.    
*   Fork child process, install signal for child, poll on child.  
*   Create socket server (like: create socket, bind, listen, set option).  
* Block on master, monitor any child process and restart who exited.  

## Tests
```shell
$ ./vendor/bin/phpunit --bootstrap=vendor/autoload.php tests
```

## Todo
Our position is focus on socket :  

* Implement protocol parse built-in.  
* Support Unix domain, UDP, ect.  
* Robustness.

## Resources:
Composer Document: https://getcomposer.org/doc/  

Symfony Console Component: http://symfony.com/doc/current/components/console.html

## Contribute:  
Any pull requests to improve **phvia/firman** are welcome.  

Coding style follow PSR2: https://www.php-fig.org/psr/psr-2/  
Using PHP_CodeSniffer tool: https://github.com/squizlabs/PHP_CodeSniffer  
Running check: `php phpcs.phar --standard=psr2 ./`  

Recommend Coding Standards: https://symfony.com/doc/current/contributing/code/standards.html

## Group
QQ group: 377154148

## License
[MIT](https://github.com/phvia/firman/blob/master/LICENSE)

