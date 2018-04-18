# via

PHP multi-process and non-blocking I/O library.

## Dependency
* PHP>=7. (use many features and high performance)
* PCNTL extension. (compile PHP with `--enable-pcntl` option to enable)
* Sockets extension. (compile PHP with `--enable-sockets` option to enable)

## Install
```shell
$ composer require phpvia/via
```

## Run example

* Show usage  
```shell
$ php /path/to/via/examples/via_websocket_serv.php

Via package 0.0.1

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
  restart  Restart Via server
  start    Start Via server
  stop     Stop Via server
```

* Show usage detail
```shell
$ php /path/to/via/examples/via_websocket_serv.php start -h

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
  Start Via server
```

* Process control
```shell
$ php /path/to/via/xxx.php start
$ php /path/to/via/xxx.php restart
$ php /path/to/via/xxx.php stop
```

* Run in daemon
```shell
$ php /path/to/via/xxx.php start --env=prod
# OR
$ php /path/to/via/xxx.php start --eprod
```

## Do it yourself

* Simplest configure
```php
(new \Via\Server('tcp://0.0.0.0:8080'))->run();
```

* All configure
```php
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
* Fork child process, install signal for child, poll on child.  
* Create socket server (like: create socket, bind, listen, set option).  
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
Any pull requests to improve **via** are welcome.  

Coding Standards: https://symfony.com/doc/current/contributing/code/standards.html

## Group
QQ group: 377154148

## License
[MIT](https://github.com/phpvia/via/blob/master/LICENSE)

