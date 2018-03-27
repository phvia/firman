<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

namespace Via;

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Server
 *
 * @package Via
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
    protected $processTitle = 'Via';

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
    protected $selectTimeout = 30;

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
     * Tip: If processes stopped by SIGSTOP(ctrl+z), use `ps auxf | grep -v grep | grep Via | awk '{print $2}' | xargs kill -CONT`
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
            throw new Exception('Error: Illegal child process number.' . PHP_EOL);
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
        $this->backlog = $backlog;

        return $this;
    }

    /**
     * Set select timeout value.
     *
     * @param int $selectTimeout
     *
     * @return $this
     */
    public function setSelectTimeout(int $selectTimeout)
    {
        $this->selectTimeout = $selectTimeout;

        return $this;
    }

    /**
     * Set accept timeout value.
     *
     * @param int $acceptTimeout
     *
     * @return $this
     */
    public function setAcceptTimeout(int $acceptTimeout)
    {
        $this->acceptTimeout = $acceptTimeout;

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
     *   (new \Via\Server('tcp://0.0.0.0:8080'))->run();
     * </code>
     *
     * @doc http://symfony.com/doc/current/components/console/single_command_tool.html
     * @doc http://symfony.com/doc/current/components/console/console_arguments.html
     *
     * @throws Exception
     */
    public function run(): void
    {
        self::strict();

        // Combine with symfony console.
        $app = new Application('Via package', self::VERSION);
        foreach ($this->commands as $cmd) {
            $app->register($cmd)
                ->setDescription(ucfirst("{$cmd} Via server"))
                ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The Environment name (support: dev, prod)', 'dev')
                ->setCode(function (InputInterface $input, OutputInterface $output) use ($cmd) {
                    if ($input->getOption('env') === 'prod') {
                        $output->writeln('<info>In production mode.</info>');
                        $this->daemon = true;
                    } else {
                        $output->writeln('<info>In development mode.</info>');
                    }

                    switch ($cmd) {
                        case 'start':

                            // Default.
                            self::initializeMaster();
                            self::createServer();
                            self::forks();
                            self::monitor();

                            break;
                        case 'restart':

                            // Quit child.
                            self::quitChild($cmd);

                            $message = "Server {$this->processTitle} {$cmd} success.";

                            exit($message . PHP_EOL);

                            break;
                        case 'stop':

                            // Quit child.
                            if (self::quitChild($cmd)) {
                                // Quit master.
                                if (posix_kill($this->ppid, SIGKILL)) {
                                    @unlink($this->serverInfo['pid_file']);
                                    $message = "Server {$this->processTitle} {$cmd} success.";
                                } else {
                                    $message = "Master {$this->processTitle} process {$this->ppid} stop failure.";
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
    protected function strict(): void
    {
        if (PHP_MAJOR_VERSION < 7) {
            // Must PHP7.
            throw new Exception("PHP major version must >= 7" . PHP_EOL);
        }

        if (! function_exists('socket_import_stream')) {
            // Must socket extension.
            throw new Exception(
                "Socket extension must be enabled at compile time by giving the '--enable-sockets' option to 'configure'" . PHP_EOL
            );
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
    protected function initializeMaster(): void
    {
        if (PHP_MINOR_VERSION >= 1) {
            // Low overhead.
            pcntl_async_signals(true);
        } else {
            // A lot of overhead.
            declare(ticks = 1);
        }

        if (self::isMasterAlive()) {
            throw new Exception("Already running, master pid {$this->ppid}, start file ({$this->serverInfo['start_file']})");
        } else {
            $this->ppid = posix_getpid();
            $this->pids[$this->ppid] = [];
            if (! file_exists($this->serverInfo['pid_file'])) {
                touch($this->serverInfo['pid_file']);
            }
            file_put_contents($this->serverInfo['pid_file'], $this->ppid, LOCK_EX);
            cli_set_process_title("{$this->processTitle} master process, start file ({$this->serverInfo['start_file']})");

            // Install signal
        }

        // TODO: notice child to quit too when parent quited.
        // TODO: when all child quit, delete pid file.
    }

    /**
     * Check if master pid alive.
     *
     * @return bool  true is alive
     *
     * @throws Exception
     */
    protected function isMasterAlive(): bool
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
                return true;
            }
        }

        return false;
    }

    /**
     * Create socket server.
     *
     * Master create socket and listen, later on descriptor can be used in child.
     * If reuse port, child can create server by itself.
     *
     * @throws Exception
     */
    protected function createServer(): void
    {
        if ($this->localSocket) {
            // Parse socket name.
            // TODO: Support Unix domain
            $list = explode(':', $this->localSocket);
            $this->protocol = $list[0] ?? null;
            $this->address  = $list[1] ? ltrim($list[1], '\/\/') : null;
            $this->port     = $list[2] ?? null;

            // Create a stream context.
            // Options see http://php.net/manual/en/context.socket.php
            // Available socket options see http://php.net/manual/en/function.socket-get-option.php
            // `Stream` extension instead of `Socket` extension in order to support fread/fwrite on connection.
            $options = [
                'socket' => [
                    'bindto'        => $this->address . ':' . $this->port,
                    'backlog'       => $this->backlog,
                    'so_reuseport'  => true,
                ],
            ];
            $params  = null;
            $context = stream_context_create($options, $params);

            // Create an Internet or Unix domain server socket.
            $errno   = 0;
            $errstr  = '';
            $flags   = ($this->protocol === 'udp') ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
            $this->socketStream  = stream_socket_server($this->localSocket, $errno, $errstr, $flags, $context);
            if (! $this->socketStream) {
                throw new Exception("Create socket server fail, errno: {$errno}, errstr: {$errstr}");
            }

            // More socket option, must install sockets extension.
            $socket = socket_import_stream($this->socketStream);

            if ($socket !== false && $socket !== null) {
                // Predefined constants: http://php.net/manual/en/sockets.constants.php
                // Level number see: http://php.net/manual/en/function.getprotobyname.php; Or `php -r "print_r(getprotobyname('tcp'));"`
                // Option name see: http://php.net/manual/en/function.socket-get-option.php
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            }

            // Switch to non-blocking mode,
            // affacts calls like fgets and fread that read from the stream.
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
     * Monitor any child process that terminated.
     *
     * If child exited or terminated, fork one.
     *
     * @throws Exception
     */
    protected function monitor(): void
    {
        // Block on master, use WNOHANG in loop will waste too much CPU.
        while ($terminated_pid = pcntl_waitpid(-1, $status, 0)) {

            if (! $this->daemon) {
                self::debugSignal($terminated_pid, $status);
            }

            unset($this->pids[$this->ppid][$terminated_pid]);

            // Fork again condition: normal exited or killed by SIGTERM.
            // if ( pcntl_wifexited($status) || (pcntl_wifsignaled($status) && in_array(pcntl_wtermsig($status), [SIGTERM])) ) {
            self::forks();
            // }
        }
    }

    /**
     * Fork child process until reach 'count' number.
     *
     * Child install signal and poll on descriptor.
     *
     * @throws Exception
     */
    protected function forks(): void
    {
        while ( empty($this->pids) || count($this->pids[$this->ppid]) < ($this->count) ) {
            self::fork();
        }
    }

    /**
     * Fork a process, install signal, and poll.
     *
     * @throws Exception
     */
    protected function fork(): void
    {
        $pid = pcntl_fork();

        switch($pid) {
            case -1:
                throw new Exception('Fork failed.');
                break;
            case 0:
                // Child process, do business, can exit at last.
                cli_set_process_title("{$this->processTitle} child process");

                self::installChildSignal();

                self::poll();

                exit();
                break;
            default:
                // Parent(master) process, not do business, cant exit.
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
    protected function installChildSignal(): void
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
                case SIGPIPE:
//                    $return_value = pcntl_signal($signo, function($signo, $siginfo) {
//                        exit();
//                    });
                    $return_value = pcntl_signal($signo, SIG_DFL);
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
     */
    protected function poll(): void
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

            // I/O multiplexing.
            // Warning raised if select system call is interrupted by an incoming signal,
            // timeout will be zero and FALSE on error.
            $value = @stream_select($read, $write, $except, $this->selectTimeout);

            if ($value > 0) {

                foreach ($this->read as $socketStream) {

                    // TODO: Timout set to zero or not.
                    // Client number greater than process count will cause status pending, so just connect cant do anything!
                    // Heartbeat mechanism, need timer.
                    // Remote address is user ip:port.
                    if (false !== ($connection = @stream_socket_accept($socketStream, 0, $remote_address))) {

                        // Connect success, callback trigger.
                        call_user_func($this->onConnection, $connection);

                        // Loop prevent read once in callback.
                        call_user_func_array($this->onMessage, [$connection]);
                    }
                }
            } elseif ($value === 0 || $value === false) {
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
        if (! self::isMasterAlive()) {
            throw new Exception("Server {$this->processTitle} not running.");
        }

        // TODO: Another goodness way
        // Find child process and quit.
        $cmd = "ps a | grep -v color | grep -v vim | grep {$this->processTitle} | awk '{print $1}'";
        exec($cmd, $output, $return_var);
        if ($return_var === 0) {
            if ($output && is_array($output)) {
                foreach ($output as $pid) {
                    // Gid equals to master pid is valid.
                    if ( ($pid != $this->ppid) && (posix_getpgid($pid) == $this->ppid) ) {

                        switch ($command_type) {
                            case 'restart':

                                // Normal quit.
                                $child_stop_status = posix_kill($pid, SIGTERM);
                                if (! $child_stop_status) {
                                    throw new Exception("Child {$this->processTitle} process {$pid} stop failure.");
                                }

                                break;
                            case 'stop':

                                // Force quit.
                                // SIGKILL cant be catch, so process will not restart.
                                $child_stop_status = posix_kill($pid, SIGKILL);
                                if (! $child_stop_status) {
                                    throw new Exception("Child {$this->processTitle} process {$pid} stop failure.");
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
    protected function debugSignal($pid, $status): void
    {
        $other_debug_signals = [
            SIGKILL => 'SIGKILL',
        ];

        $message = "Process[{$pid}] quit, ";

        if (pcntl_wifexited($status)) {
            $message .= "Normal exited with status " . pcntl_wexitstatus($status) . ", line " . __LINE__;
        }

        if (pcntl_wifsignaled($status)) {
            $message .= "by signal " .
                ($this->signals[ pcntl_wtermsig($status) ] ?? ($other_debug_signals[pcntl_wtermsig($status)] ?? 'Unknow')) . "(" . pcntl_wtermsig($status) . "), line " . __LINE__;
        }

        if (pcntl_wifstopped($status)) {
            $message .= "by signal (" .
                pcntl_wstopsig($status) . "), line " . __LINE__;
        }

        echo $message . PHP_EOL;
    }

}
