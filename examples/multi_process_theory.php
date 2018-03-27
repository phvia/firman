<?php
/**
 * Multi process program theory.
 *
 * @see https://github.com/farwish/alcon/blob/master/src/Supports/Helper.php#L477
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

$count = 4;
$pids  = [];

echo "Current posix_getpid is " . posix_getpid() . PHP_EOL;

for ($i = 0; $i < $count; $i++) {

    $pid = pcntl_fork();

    switch ($pid) {
        case -1:
            throw new \Exception("Fork failed.\n");
            break;
        case 0:
            // Child.
            sleep(1);
            $rand = rand(2, 25);
            echo "Child posix_getpid is " . posix_getpid() . " now working. Will waste {$rand} seconds" . PHP_EOL;
            sleep($rand);
            die;
            break;
        default:
            // Parent.
            echo "Parent posix_getpid is " . posix_getpid() . PHP_EOL;
            echo "Parent pid is " . $pid . PHP_EOL;
            $pids[$pid] = $pid;
            break;
    }
}

print_r($pids);

// Monitor

// WARNING: the child exit order is fixed, it not right, because waitpid will block wait the specified process to terminate using WUNTRACED.

//$num = 0;
//foreach ($pids as $pid) {
    //$exited_child_pid = pcntl_waitpid($pid, $status, WUNTRACED);
    //if ($exited_child_pid) {
        //echo "Child {$pid} finish working exited - " . date('Y-m-d H:i:s') . PHP_EOL;
        //$num++;
    //}
//}

// RIGHT: use WNOHANG with a loop instead.

$num = 0;
do {
    foreach ($pids as $pid) {
        $exited_child_pid = pcntl_waitpid($pid, $status, WNOHANG);
        if ($exited_child_pid > 0) {
            unset($pids[$exited_child_pid]);
            echo "Child {$pid} finish working exited." . PHP_EOL;

            if (pcntl_wifexited($status)) {
                echo "Normal exit.\n";
            }

            if (pcntl_wifsignaled($status)) {
                echo "Signal kill. Signal number: " . pcntl_wtermsig($status) . PHP_EOL;
            }

            if (pcntl_wifstopped($status)) {
                echo "stop by signal, Currently stopped. Signal number: " . pcntl_wstopsig($status) . PHP_EOL;
            }

            $num++;
        }
    }
} while ( count($pids) > 0 );

echo "Total {$num} child exited." . PHP_EOL;

/*
    fork 返回两次，0 时是子进程空间，大于0是父进程空间。

    从以下输出得出结论：
        Parent 分支其实是在主进程内执行的，这里面的 pid 就是子进程id。
        为了不阻塞主进程(只监控子进程状态)，所以任务都是在 Child 分支内执行，子进程可以通过 posix_getpid() 得到自己的进程id.
        Child 模拟不同的耗时任务，如果主(父)进程已经执行完毕后不等待，各个子进程执行完毕后，调用退出结束自身进程：
            这种情况，在 Child 进程全部退出前主进程已经执行完退出了，那么子进程就成了孤儿进程; 所以需要 waitpid 让主进程阻塞以等待子进程退出。
        一直运行不退出的服务器程序，那么就需要主进程对子进程状态进行监控，需要始终运行(无限循环监听)或者一个子进程满足一定条件后退出然后重新拉起防止内存泄露。
        监控子进程终止，循环 $pids 调用 waitpid，用 WUNTRACED 会阻塞在等待的 pid 上，所以输出的顺序是有序的;
            而 WNOHANG 使 waitpid 不阻塞，配合轮询检测，子进程一有终止就输出。

    Current posix_getpid is 75054
    Parent posix_getpid is 75054
    Parent pid is 75055
    Parent posix_getpid is 75054
    Parent pid is 75056
    Parent posix_getpid is 75054
    Parent pid is 75057
    Parent posix_getpid is 75054
    Parent pid is 75058
    Array
    (
        [75055] => 75055
        [75056] => 75056
        [75057] => 75057
        [75058] => 75058
    )
    Child posix_getpid is 75058 now working. Will waste 2 seconds
    Child posix_getpid is 75055 now working. Will waste 21 seconds
    Child posix_getpid is 75056 now working. Will waste 10 seconds
    Child posix_getpid is 75057 now working. Will waste 24 seconds
    Child 75058 finish working exited.
    Child 75056 finish working exited.
    Child 75055 finish working exited.
    Child 75057 finish working exited.
    Total 4 child exited.


    Final Tip:
        Die at end of child when doing task.
        Use waitpid option WNOHANG.
*/
