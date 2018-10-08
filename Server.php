<?php
/**
 * Firman package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

namespace Firman;

use Exception;
use Firman\Connection;
use Firman\Protocol\WebSocket;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Server
 *
 * @package Firman
 */
class Server
{
    const VERSION = '0.0.1';

    /**
     * Child process number.
     *
     * Master has one child process by default.
     *
     * @var int $count
     */
    protected $count = 1;

    /**
     * Internet address or Unix domain.
     *
     * @var string $localSocket
     */
    protected $localSocket = null;

    /**
     * Stream return by stream_socket_server.
     *
     * @var Resource $socketStream
     */
    protected $socketStream = null;

    /**
     * Protocol of socket.
     *
     * @var string $protocol
     */
    protected $protocol = null;

    /**
     * Address of socket.
     *
     * @var string $address
     */
    protected $address = null;

    /**
     * Port of socket.
     *
     * @var int $port
     */
    protected $port = null;

    /**
     * Process title.
     *
     * @var string $processTitle
     */
    protected $processTitle = 'Firman';

    /**
     * Max client number waited in socket queue.
     *
     * @var int $backlog
     */
    protected $backlog = 100;

    /**
     * Socket select timeout (seconds)
     *
     * @var float $selectTimeout
     */
    protected $selectTimeout = 200;

    /**
     * Socket accept timeout (seconds).
     *
     * @var float $acceptTimeout
     */
    protected $acceptTimeout = 60;

    /**
     * Connection callback function.
     *
     * @var callable $onConnection
     */
    protected $onConnection = null;

    /**
     * Message callback function.
     *
     * @var callable $onMessage
     */
    protected $onMessage = null;

    /**
     * Usable command.
     *
     * @var array $commands
     */
    protected $commands = [
        'start',
        'restart',
        'stop',
    ];

    /**
     * Is in daemon.
     *
     * @var bool $daemon
     */
    protected $daemon = false;

    /**
     * The path of file saved ppid.
     *
     * @var string $ppidPath
     */
    protected $ppidPath = '/tmp';

    /**
     * Parent process id
     *
     * @var int $ppid
     */
    protected $ppid = null;

    /**
     * Child process id container.
     *
     * Format likes: [ 72506 => [ 72507 => 72507, 72508 => 72507 ] ]
     *               [ ppid  => [  pid1 =>  pid1,  pid2 => pid2  ] ]
     *
     * @var array $pids
     */
    protected $pids = [];

    /**
     * Monitored signals.
     *
     * Tip: If processes stopped by SIGSTOP(ctrl+z), use `ps auxf | grep -v grep | grep Firman | awk '{print $2}' | xargs kill -CONT`
     * recover from `T` to `S`.
     *
     * @var array $signals
     */
    protected $signals = [
        SIGINT  => 'SIGINT',  // 2   interrupted by keyboard (ctrl+c).
        SIGQUIT => 'SIGQUIT', // 3   quit by keyboard (ctrl+\).
        SIGUSR1 => 'SIGUSR1', // 10  custom
        SIGUSR2 => 'SIGUSR2', // 12  custom
        SIGPIPE => 'SIGPIPE', // 13  write to broken pipe emit it and process exit.
        SIGTERM => 'SIGTERM', // 15  terminated by `kill pid`, note that SIGKILL(9) and SIGSTOP(19) cant be caught.
        SIGCHLD => 'SIGCHLD', // 17  exited normal between one child.
    ];

    /**
     * Server information.
     *
     * @var array
     */
    protected $serverInfo = [
        'start_file'     => '',
        'pid_file'       => '',
    ];

    /**
     * Constructor.
     *
     * Supported socket transports.
     * @see http://php.net/manual/en/transports.php
     *
     * @param string $socket
     */
    public function __construct(string $socket = '')
    {
        $this->localSocket = $socket ?: null;

        $this->onConnection = function() {};
        $this->onMessage    = function() {};
    }

    /**
     * Set child process number.
     *
     * @param int $count
     *
     * @return $this
     * @throws Exception
     */
    public function setCount(int $count)
    {
        if ((int)$count > 0) {
            $this->count = $count;
        } else {
            throw new Exception('Error: Illegal child process number.');
        }

        return $this;
    }

    /**
     * Set socket.
     *
     * Use this function or initialize socket in Constructor.
     *
     * @param string $socket
     *
     * @return $this
     */
    public function setSocket(string $socket)
    {
        $this->localSocket = $socket;

        return $this;
    }

    /**
     * Set process title.
     *
     * @param string $title
     *
     * @return $this
     */
    public function setProcessTitle(string $title)
    {
        if ($title) $this->processTitle = $title;

        return $this;
    }

    /**
     * Set the path of file saved ppid.
     *
     * @param string $path
     *
     * @return $this
     */
    public function setPpidPath(string $path)
    {
        $this->ppidPath = $path;

        return $this;
    }

    /**
     * Set socket backlog number.
     *
     * @param int $backlog
     *
     * @return $this
     */
    public function setBacklog(int $backlog)
    {
        if ($backlog > 0) $this->backlog = $backlog;

        return $this;
    }

    /**
     * Set select timeout value (seconds).
     *
     * @param int $selectTimeout
     *
     * @return $this
     */
    public function setSelectTimeout(int $selectTimeout)
    {
        if ($selectTimeout >= 0) $this->selectTimeout = $selectTimeout;

        return $this;
    }

    /**
     * Set accept timeout value (seconds).
     *
     * @param int $acceptTimeout
     *
     * @return $this
     */
    public function setAcceptTimeout(int $acceptTimeout)
    {
        if ($acceptTimeout >= 0) $this->acceptTimeout = $acceptTimeout;

        return $this;
    }

    /**
     * Set connection event callback task.
     *
     * @param callable $callback  the first param is $connection return by accept.
     *
     * @return $this
     */
    public function onConnection(callable $callback)
    {
        $this->onConnection = $callback;

        return $this;
    }

    /**
     * Set message event callback task.
     *
     * @param callable $callback  the first param is $connection, second param is data.
     *
     * @return $this
     */
    public function onMessage(callable $callback)
    {
        $this->onMessage = $callback;

        return $this;
    }

    /**
     * Parse command and option.
     *
     * <code>
     *   (new \Firman\Server('tcp://0.0.0.0:8080'))->run();
     * </code>
     *
     * @doc http://symfony.com/doc/current/components/console/single_command_tool.html
     * @doc http://symfony.com/doc/current/components/console/console_arguments.html
     *
     * @throws Exception
     */
    public function run()
    {
        global $argv;

        self::strict();

        // Combine with symfony console.
        $app = new Application("PHP multi-process and non-blocking I/O library.\nFirman package", self::VERSION);
        foreach ($this->commands as $cmd) {
            $app->register($cmd)
                ->setDescription(ucfirst("{$cmd} Firman server"))
                ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Environment name [dev,prod], dev in foreground, prod in daemon', 'dev')
                ->setCode(function (InputInterface $input, OutputInterface $output) use ($cmd, $argv) {
                    if ($input->getOption('env') === 'prod') {
                        $this->daemon = true;
                    }

                    switch ($cmd) {
                        case 'start':

                            // Daemonize if needed.
                            if ($this->daemon){
                                self::daemonize();
                            }

                            // Initialize global information for master.
                            self::initializeMaster();

                            // Create socket, Bind and Listen.
                            // If no reuseport, bind and listen here, this will cause thundering herd problem, process not available still be waked up.
                            //      select(7, [6], [6], [], {5, 0})         = 0 (Timeout)
                            //      select(7, [6], [6], [], {5, 0})         = 1 (in [6], left {2, 22060})
                            //    * poll([{fd=6, events=POLLIN|POLLERR|POLLHUP}], 1, 10000) = 1 ([{fd=6, revents=POLLIN}])
                            //    * accept(6, 0x7ffe34ffe390, 0x7ffe34ffe380) = -1 EAGAIN (Resource temporarily unavailable)
                            //    * poll([{fd=6, events=POLLIN|POLLERR|POLLHUP}], 1, 10000) = 0 (Timeout)
                            //      select(7, [6], [6], [], {5, 0})         = 0 (Timeout)
                            // So bind and listen in child when reuseport.
                            // self::createServer();

                            // Fork workers.
                            self::forkUntilReach();

                            // Success tip after fork.
                            if ($this->daemon) {
                                $output->writeln("In production mode, daemon [<info>on</info>].");
                                $output->writeln(sprintf('Start success, input <info>php %s stop</info> to quit.', $argv[0]));
                            } else {
                                $output->writeln("In development mode, daemon [<comment>off</comment>].");
                                $output->writeln('Start success, press <info>Ctrl + C</info> to quit.');
                            }

                            // Monitor any child stopped.
                            self::monitor();

                            break;
                        case 'restart':

                            // Quit child.
                            if (self::quitChild($cmd)) {
                                $message = sprintf('Server %s %s success.', $this->processTitle, $cmd);

                                exit($message . PHP_EOL);
                            }

                            break;
                        case 'stop':

                            // Quit child.
                            if (self::quitChild($cmd)) {
                                // Quit master.
                                if (posix_kill($this->ppid, SIGKILL)) {
                                    @unlink($this->serverInfo['pid_file']);
                                    $message = sprintf("Server %s %s success.", $this->processTitle, $cmd);
                                } else {
                                    $message = sprintf('Master %s process %s stop failure.', $this->processTitle, $this->ppid);
                                }

                                exit($message . PHP_EOL);
                            }

                            break;
                        default:
                            break;
                    }
                });
        }

        $app->run();
    }

    /**
     * Use strict.
     *
     * @throws Exception
     */
    protected function strict()
    {
        if (PHP_MAJOR_VERSION < 7) {
            // Must PHP7.
            throw new Exception('PHP major version must >= 7');
        }

        if (! function_exists('socket_import_stream')) {
            // Must socket extension.
            throw new Exception('Socket extension must be enabled at compile time by giving the "--enable-sockets" option to "configure"');
        }
    }

    /**
     * Initialize master process.
     *
     * Parent catch the signal, child will extends parent signal handler.
     * But it not means child will receive the signal too, SIGTERM is
     * exception, if parent catch SIGTERM, child will not received, so this
     * signal should be reinstall in the child.
     *
     * If child process terminated, monitor will fork again.
     *
     * PCNTL signal constants:
     * @see http://php.net/manual/en/pcntl.constants.php
     *
     * @throws Exception
     */
    protected function initializeMaster()
    {
        if (PHP_MINOR_VERSION >= 1) {
            // Low overhead.
            pcntl_async_signals(true);
        } else {
            // A lot of overhead.
            declare(ticks = 1);
        }

        if (self::isMasterAlive()) {
            // Already running.
            throw new Exception(sprintf('Already running, master pid %s, start file (%s)', $this->ppid, $this->serverInfo['start_file']));
        } else {
            // Init ppid and pids container.
            $this->ppid = posix_getpid();
            $this->pids[$this->ppid] = [];

            // Init start file and pid file.
            $start_file = $this->serverInfo['start_file'];
            $pid_file = $this->serverInfo['pid_file'];
            if (! file_exists($pid_file)) {
                touch($pid_file);
            }
            file_put_contents($pid_file, $this->ppid, LOCK_EX);

            // Init master process title.
            cli_set_process_title(sprintf('%s master process, start file (%s)', $this->processTitle, $start_file));

            // Init signal(SIGINT) handler for ctrl+c action.
            $return_value = pcntl_signal(SIGINT, function($signo, $siginfo) use ($pid_file) {
                @unlink($pid_file);
                exit(0);
            });
            if (! $return_value) {
                throw new Exception('Install signal(SIGINT) failed.');
            }
        }

        // TODO: notify child to quit too when parent quited.
    }

    /**
     * Check if master pid alive.
     *
     * @return int|bool return master pid if alive or false
     *
     * @throws Exception
     */
    protected function isMasterAlive()
    {
        $backtrace = debug_backtrace();

        // Log some info.
        $this->serverInfo['start_file'] = end($backtrace)['file'];
        $this->serverInfo['pid_file'] = rtrim($this->ppidPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . str_replace([DIRECTORY_SEPARATOR, '.'], ['_', '_'], $this->serverInfo['start_file']) . '.pid';

        if (file_exists($this->serverInfo['pid_file'])) {
            // Ping.
            $this->ppid = (int)file_get_contents($this->serverInfo['pid_file']);
            if ($this->ppid && posix_kill($this->ppid, 0)) {
                return (int)$this->ppid;
            }
        }

        return false;
    }

    /**
     * Create socket server.
     *
     * Master create socket and listen, later on descriptor can be used in child.
     * If reuse port, child can create server by itself, it can resolve thundering herd.
     *
     * Important functions:
     * stream_context_create => stream_socket_server => socket_import_stream => socket_set_option => stream_set_blocking
     * stream_socket_server equals to execute create, bind, listen in order.
     *
     * @throws Exception
     */
    protected function createServer()
    {
        if ($this->localSocket) {
            // Parse socket name like `tcp://0.0.0.0:8090`.
            // TODO: Support Unix domain
            $list = explode(':', $this->localSocket);
            $this->protocol = $list[0] ?? null;
            $this->address  = $list[1] ? ltrim($list[1], '\/\/') : null;
            $this->port     = $list[2] ?? null;

            // `Stream` extension instead of `Socket` extension in order to support fread/fwrite on connection.
            //
            // 封装体可用的少量套接字选项.
            // Available Socket Context Options for all wrappers.
            // @doc http://php.net/manual/en/context.socket.php
            $options = [
                'socket' => [
                    // The syntax is ip:port for IPv4, and [ip]:port for IPv6.
                    // Setting the IP or port to 0 will let the system choose
                    // the IP and/or port.
                    'bindto'        => $this->address . ':' . $this->port,

                    // Used to liimit the number of outstanding connections in the socket's listen queue.
                    'backlog'       => $this->backlog,

                    // PHP7.0.1
                    // Overrides the OS default regarding mapping IPv4 into IPv6.
                    //'ipv6_v6only'   => '',

                    // 7.0.0
                    // Allows multiple bindings to a same ip:port pair.
                    // Avoid thundering herd.
                    'so_reuseport'  => true,

                    // PHP7.0.0
                    // Enables sending and receiving data to/from broadcast addresses.
                    //'so_broadcast'  => '',

                    // PHP7.1.0
                    // Setting this to TRUE will set SOL_TCP,NO_DELAY=1
                    // appropriately, thus disabling the TCP Nagle algorithm.
                    //'tcp_nodelay'   => true,
                ],
            ];
            $params  = null;
            $context = stream_context_create($options, $params);

            // Creates a stream or datagram socket on the specified local socket.
            $errno   = 0;
            $errstr  = '';
            $flags   = ($this->protocol === 'udp') ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
            $this->socketStream  = stream_socket_server($this->localSocket, $errno, $errstr, $flags, $context);
            if (! $this->socketStream) {
                throw new Exception(sprintf('Create socket server fail, errno: %s, errstr: %s', $errno, $errstr));
            }

            // 把封装了套接字的流导入到socket扩展的资源中.
            // Imports a stream that encapsulates a socket into a socket extension resource.
            $socket = socket_import_stream($this->socketStream);

            if ($socket !== false && $socket !== null) {

                // 套接字可用的全量套接字选项.
                // Available Socket Options for the socket.
                // @doc http://php.net/manual/en/function.socket-get-option.php

                // Level number: `php -r "print_r(getprotobyname('tcp'));"`, SOL_TCP==6
                // @doc http://php.net/manual/en/function.getprotobyname.php;

                // Connections are kept active with periodic transmission of messages,
                // if the connected socket fails to respond to these messages,
                // the connection is broken and processes writing to that socket are notified with a SIGPIPE signal.
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

                // Nagle TCP algorithm is disabled.
                socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            }

            // Switch to non-blocking mode, affacts calls like fgets and fread that read from the stream.
            // In non-blocking mode, an fgets call will always return right away.
            if (! stream_set_blocking($this->socketStream, false)) {
                throw new Exception('Switch to non-blocking mode fail');
            }

            // Store current process his socket stream.
            $this->read[]  = $this->socketStream;
            $this->write[] = $this->socketStream;
            $this->except  = [];
        }
    }

    /**
     * Daemonize the current process.
     *
     * @see APUE 13.3 part
     */
    protected function daemonize()
    {
        // 1.1
        $pid = pcntl_fork();

        switch ($pid) {
            case -1:
                throw new Exception('Fork failed');
                break;
            case 0:
                // Child

                // 2.
                // setsid: Make the current process a session leader to lose controlling TTY.
                if (-1 === ($sid = posix_setsid())) {
                    throw new Exception('Daemonize setsid error');
                }

                // 3.
                // Change current working directory to the root
                // so we won't prevent file systems from being unmounted.
                if (false === chdir('/')) {
                    throw new Exception(sprintf('Daemonize change working directory to "%s" failed', '/'));
                }

                // 4.
                umask(0);

                // 5.
                fclose(STDIN);
                fclose(STDOUT);
                fclose(STDERR);

                // Open descriptor if you need later.
                //fopen('/dev/null', 'rw');

                break;
            default:
                // Master

                // 1.2
                // Let shell think it execute finished.
                exit(0);
        }
    }

    /**
     * Monitor any child process that terminated.
     *
     * If child exited or terminated, fork one.
     *
     * @throws Exception
     */
    protected function monitor()
    {
        // Block on master, use WNOHANG in loop will waste too much CPU.
        while ($terminated_pid = pcntl_waitpid(-1, $status, 0)) {

            unset($this->pids[$this->ppid][$terminated_pid]);

            if (! $this->daemon) {
                self::debugSignal($terminated_pid, $status);
            }

            // TODO Do statistics here.

            // Fork again condition: normal exited or killed by SIGTERM.
            // if ( pcntl_wifexited($status) || (pcntl_wifsignaled($status) && in_array(pcntl_wtermsig($status), [SIGTERM])) ) {
            self::forkUntilReach();
            // }
        }
    }

    /**
     * Fork child process until reach 'count' number.
     *
     * Child install signal and poll on theirself descriptor.
     *
     * @throws Exception
     */
    protected function forkUntilReach()
    {
        while ( empty($this->pids) || (count($this->pids[$this->ppid]) < $this->count) ) {
            self::forkWorker();
        }
    }

    /**
     * Fork a process, install signal, and poll.
     *
     * @throws Exception
     */
    protected function forkWorker()
    {
        $pid = pcntl_fork();

        switch ($pid) {
            case -1:
                throw new Exception('Fork worker failed.');
                break;
            case 0:
                // Set child process title.
                cli_set_process_title(sprintf('%s worker process', $this->processTitle));

                // Create socket, bind, listen.
                self::createServer();

                // Install child signals.
                self::installChildSignal();

                // Select multiplexing, accept connection, doing work.
                self::poll();

                exit(0);
                break;
            default:
                // Parent(master) process, not do business, can not exit.
                $this->pids[$this->ppid][$pid] = $pid;
                break;
        }
    }

    /**
     * Install signal handler in child process.
     *
     * If child process terminated, monitor will fork again.
     *
     * PCNTL signal constants:
     * @see http://php.net/manual/en/pcntl.constants.php
     *
     * @throws Exception
     */
    protected function installChildSignal()
    {
        $return_value = true;
        foreach ($this->signals as $signo => $name) {
            // Will extend parent handler first.
            switch ($signo) {
                case SIGUSR1:

                    break;
                case SIGUSR2:

                    break;
                case SIGINT:
                case SIGQUIT:
                case SIGTERM:
                case SIGCHLD:
                    $return_value = pcntl_signal($signo, SIG_DFL);
                    break;
                case SIGPIPE:
                    $return_value = pcntl_signal($signo, SIG_IGN);
                    break;
                default:
                    break;
            }

            if (! $return_value) {
                throw new Exception('Install signal failed.');
            }
        }
        unset($return_value);
    }

    /**
     * Poll on all child process.
     *
     * Important functions:
     *  stream_select => stream_socket_accept
     */
    protected function poll()
    {
        // Store child socket stream.
        $this->read[]  = $this->socketStream;
        $this->write[] = $this->socketStream;
        $this->except  = [];

        do {
            // Stream_select need variable reference, so reassignment.
            $read = $this->read;
            $write = $this->write;
            $except = $this->except;

            // Synchronous I/O multiplexing: select / poll / epoll.
            // Waits for one of a set of file descriptors to become ready to perform I/O.
            // Warning raised if select system call is interrupted by an incoming signal,
            //  timeout on zero, FALSE on error.
            // `man 2 select` seek more information if needed.
            $number = @stream_select($read, $write, $except, $this->selectTimeout);

            if ($number > 0) {

                foreach ($read as $socket_stream) {

                    // TODO Heartbeat mechanism need timer.

                    // If no pending connections: blocking I/O socket - accept() blocks the caller until a connection is present.
                    //                         nonblocking I/O socket - accept() fails with the error EAGAIN or EWOULDBLOCK.
                    // In order to be notified of incoming connections on a socket, we can use select(2) or poll(2).
                    // Remote_address is set to user ip:port.
                    // `man 2 accept` seek more information if needed.
                    if (false !== ($socket_connection = @stream_socket_accept($socket_stream, $this->acceptTimeout, $remote_address))) {

                        // Set read operations unbuffered.
                        stream_set_read_buffer($socket_stream, 0);

                        // Connect success, callback trigger.
                        call_user_func($this->onConnection);

                        // Do handshake, auto judge if handshake yet.
                        if (WebSocket::doHandshake($socket_connection)) {
                            while (true) {
                                $decoded_string = WebSocket::decode($socket_connection);
                                if ($decoded_string) {
                                    call_user_func_array($this->onMessage, [new Connection($socket_connection), $decoded_string]);
                                }
                            }
                        }
                    }
                }
            } elseif ($number === 0 || $number === false) {
                // Timeout or Error
                continue;
            } else {
            }
        } while (true);
    }

    /**
     * Quit all child.
     *
     * @param string $command_type  example: kill, stop
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function quitChild(string $command_type): bool
    {
        if (! $this->ppid = self::isMasterAlive()) {
            throw new Exception(sprintf('Server %s not running.', $this->processTitle));
        }

        // TODO: Another goodness way
        // Find child process and quit.
        $cmd = "ps --ppid {$this->ppid} | awk '/[0-9]/{print $1}'";
        exec($cmd, $output, $return_var);
        if ($return_var === 0) {
            if ($output && is_array($output)) {
                foreach ($output as $pid) {

                    // Gid equals to master pid is valid.
                    if ( posix_getpgid($pid) == $this->ppid ) {

                        switch ($command_type) {
                            case 'restart':

                                // Normal quit to auto restart by monitor.
                                $child_stop_status = posix_kill($pid, SIGTERM);
                                if (! $child_stop_status) {
                                    throw new Exception(sprintf('Child %s process %s stop failure.', $this->processTitle, $pid));
                                }

                                break;
                            case 'stop':

                                // Force quit.
                                // SIGKILL cant be catch, so process will not restart.
                                $child_stop_status = posix_kill($pid, SIGKILL);
                                if (! $child_stop_status) {
                                    throw new Exception(sprintf('Child %s process %s stop failure.', $this->processTitle, $pid));
                                }

                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Output info when monitor child quit.
     *
     * `kill -TERM 80382` as kill 80382
     * `kill -STOP 80382` stop a process, use -CONT to recover.
     * `kill -CONT 80382` continue a process stopped.
     *
     * @param $pid
     * @param $status
     */
    protected function debugSignal($pid, $status)
    {
        $other_debug_signals = [
            SIGKILL => 'SIGKILL',
        ];

        $message = sprintf('Process[%s] quit, ', $pid);

        if (pcntl_wifexited($status)) {
            $message .= sprintf('Normal exited with status %s', pcntl_wexitstatus($status));
        }

        if (pcntl_wifsignaled($status)) {
            $message .= sprintf('by signal %s (%s)', ($this->signals[ pcntl_wtermsig($status) ] ?? ($other_debug_signals[pcntl_wtermsig($status)] ?? 'Unknow')), pcntl_wtermsig($status));
        }

        if (pcntl_wifstopped($status)) {
            $message .= sprintf('by signal (%s)', pcntl_wstopsig($status));
        }

        echo $message . PHP_EOL;
    }

}
