<?php

namespace yii2crontab\crontab;

defined('BIN_PATH') || define('BIN_PATH', CRONTAB_ROOT . '/yii ');

use Yii;
use yii\console\Controller;
use common\component\Helpers;

class Task extends Controller
{
    protected $try = 0;
    protected $tryLimit = 10;
    protected $date;
    protected $defaultSleepSeconds = 1;
    protected $queuemsg = null;

    /**
     * 获取控制台任务名
     *
     * @param null $action
     * @return string
     */
    protected function getTaskName($action = null)
    {
        $controller = $this->id;
        $action = $action === null ? $this->action->id : $action;

        return $controller . '/' . $action;
    }

    public function actionRun()
    {
        echo 'run' . PHP_EOL;
    }

    /**
     * 停止控制台任务
     *
     * @param string $action
     */
    public function actionStop($action = 'run')
    {
        $task = $this->getTaskName($action);
        $num = System::getProcessNum($task);
        if ($num > 0) {
            echo "stop {$task} ...";
            echo $this->sendSign($task, 'stop', $num);
            // 等待进程关闭
            do {
                echo ".";
                sleep(1);
                $num = System::getProcessNum($task);
            } while ($num > 0);
            echo "ok\n";
        }
    }

    /**
     * 重启控制台任务
     *
     * @param string $action
     */
    public function actionRestart($action = 'run')
    {
        $task = $this->getTaskName($action);
        $num = System::getProcessNum($task);

        // 关闭进程
        $this->actionStop($action);

        // 启动进程
        echo "start {$task}\n";
        if ($num < 1) {
            $num = 1;
        }
        System::start(CRONTAB_ROOT . '/yii', $task, $num, '/dev/null', '');
    }

    /**
     * 发送信号
     *
     * @param $task
     * @param string $sign
     * @param int $num
     * @param bool $hset
     * @return int
     */
    public function sendSign($task, $sign = 'stop', $num = 1, $hset = false)
    {
        $hashKey = BIN_PATH . $task . ':' . $sign;
        $rt = 0;
        if ($num > 1 || $hset) {
            $rt = $num;
            Yii::$app->redis->hset('CRONTAB|SIGN', $hashKey, $num);

            echo 'sendSign ' . $num;
        } else {
            $rt = Yii::$app->redis->hincrby('CRONTAB|SIGN', $hashKey, $num);

            echo 'hincrby ' . $num;
        }

        return $rt;
    }

    /**
     * 获取信号
     *
     * @param $task
     * @param string $sign
     * @return mixed
     */
    public function fetchSign($task, $sign = 'stop')
    {
        return Yii::$app->redis->hget('CRONTAB|SIGN', BIN_PATH . $task . ':' . $sign);
    }

    /**
     * 检查信号, 关闭进程或者重启
     *
     * @param bool $checkDate
     */
    protected function checkSign($checkDate = true)
    {
        // 接收关闭进程信号
        $task = $this->getTaskName();

        $sign = $this->fetchSign($task, 'stop');

        echo "checkSign {$task} {$sign}..";

        if ($sign > 0) {
            $this->sendSign($task, 'stop', -1);
            echo "exit ..\n";
            exit();
        }

        // 隔天自动重启
        if (empty($this->date)) {
            $this->date = date('Ymd');
        }
        if ($checkDate && $this->date != date('Ymd')) {
            echo "exit ..\n";
            exit();
        }

        echo "\n";
    }

    /**
     * 记录日志
     *
     * @param $msg
     * @param string $tail
     * @param string $user_category
     */
    public function log($msg, $tail = "\n", $user_category = '')
    {
        if (empty($user_category)) {
            $user_category = str_replace('\\', '_', get_called_class());
        }

        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }

        Helpers::ickeyInfo($msg, $user_category);
    }

    /**
     * 日志信息加入队列
     *
     * @param $data
     */
    public function queueLog($data)
    {
        Yii::$app->queue->send(Yii::$app->params['queueKey']['queue_log'], $data);
    }

    /**
     * 记录错误信息
     *
     * @param $data
     * @param $error
     * @param string $tail
     * @param string $user_category
     */
    public function error($data, $error, $tail = "\n", $user_category = '')
    {
        if (empty($user_category)) {
            $user_category = str_replace('\\', '_', get_called_class());
        }

        $msg = '[fatal_error]:' . $error . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);

        Helpers::ickeyError($msg, $user_category);
    }

    /**
     * 队列处理完后的操作
     *
     * @param null $queue
     * @param int $seconds
     * @param bool $spects
     */
    public function end($queue = null, $seconds = 0, $spects = false)
    {
        // 队列结束处理
        if (null !== $queue) {
            $this->heartbeat(0);
            $queue->end();
        }

        // 检查是否终止或者重启
        $this->checkSign();

        // 处理 sleep
        if ($seconds > 0) {
            if ($spects) {
                $this->iSleepToTime($seconds);
            } else {
                $this->iSleep($seconds);
            }
        }
    }

    /**
     * 休眠一段时间
     *
     * @param $seconds
     */
    public function iSleep($seconds)
    {
        $start_time = time();
        $end_time = $start_time + $seconds;

        while ($end_time - $start_time > 0) {
            $this->heartbeat($seconds);
            $this->checkSign(false);
            sleep($this->defaultSleepSeconds);
            $start_time = time();
        }
    }

    /**
     * 记录心跳信息 ?
     *
     * @param int $seconds
     */
    protected function heartbeat($seconds = 0)
    {
        $dir = CRONTAB_LOG . '/../status/';
        $task_id = $this->id;
        $task_id = str_replace('/', '-', $task_id);
        $mem = memory_get_usage();
        $maxmem = memory_get_peak_usage();

        $content = [
            'time_now' => time(),
            '@timestamp' => date('c'),
            'mem' => $mem,
            'maxmem' => $maxmem,
            'type' => 'task',
            'task_name' => $task_id . '|' . $this->action->id,
            'class' => get_called_class(),
            'sleep' => $seconds
        ];

        file_put_contents($dir . $task_id . '-' . $this->action->id, json_encode($content));
    }

    /**
     * 休眠到指定时间点
     *
     * @param $date_time_str
     */
    public function iSleepToTime($date_time_str)
    {
        $start_time = time();
        $end_time = strtotime($date_time_str);

        $delta_seconds = $end_time - $start_time;
        $this->iSleep($delta_seconds);
    }

    /**
     * 指定时间启动定时器
     * @param  boolean $force_run [description]
     * @param  string $up_date_str [description]
     * @param  string $up_time_str [description]
     * @param  string $delta_type [description]
     * @param  integer $delta_value [description]
     * @return [type]               [description]
     */
    public function iSleepTimerUp($force_run = true, $up_date_str = '', $up_time_str = '', $delta_type = 'sencond', $delta_value = 1)
    {
        if (empty($up_date_str)) {
            $up_date_str = date('Y-m-d');
        }

        if (empty($up_time_str)) {
            $up_time_str = date('H:i:s');
        }

        $final_date_str = $up_date_str . ' ' . $up_time_str;

        $final_time = strtotime($final_date_str);

        $add_time = 0;
        if ($delta_type == 'sencond') {
            $add_time = $delta_value;
        } else if ($delta_type == 'minute') {
            $add_time = 60 * $delta_value;
        } else if ($delta_type == 'hour') {
            $add_time = 60 * 60 * $delta_value;
        } else if ($delta_type == 'day') {
            $add_time = 24 * 60 * 60 * $delta_value;
        }

        $nex_time = $final_time + $add_time;
        $next_date_str = date('Y-m-d H:i:s', $nex_time);

        $now_time = time();

        $delta_time = $now_time - $final_time;

        if ($delta_time >= 0) {
            if ($force_run) {
                $this->iSleepToTime($next_date_str);
            }
        } else {
            $this->iSleep(abs($delta_time));
        }
    }

}
