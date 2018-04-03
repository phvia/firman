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
```shell
# Show usage

$ php /path/to/via/examples/via_websocket_serv.php
```

```shell
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

```shell
# Show usage detail

$ php /path/to/via/examples/via_websocket_serv.php start -h
```

```shell
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

```shell
# Process control

$ php /path/to/via/xxx.php start
$ php /path/to/via/xxx.php restart
$ php /path/to/via/xxx.php stop
```

## Do it yourself
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

## Server work flow explain

* Check environment.  
* Parse command.  
* Initialize master process information.  
* Create socket server.  
* Fork child process, install signal for child, poll on child.  
* Block on master, monitor any child process exited to reload it.  

## Tests
```shell
$ ./vendor/bin/phpunit --bootstrap=vendor/autoload.php tests
```

## Contribute:  
Coding Standards: https://symfony.com/doc/current/contributing/code/standards.html

## Other resource:  
Composer document: https://getcomposer.org/doc/  
Symfony Console Component: http://symfony.com/doc/current/components/console.html

## Group
QQ group: 377154148

## Todo
Our position is focus on socket :  

Implement protocol parse built-in.  
Support Unix domain, UDP.  
Robustness.  

## License
[MIT](https://github.com/phpvia/via/blob/master/LICENSE)

