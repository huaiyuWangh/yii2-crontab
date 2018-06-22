<?php

namespace yii2crontab\crontab;
defined('BIN_PATH') || define('BIN_PATH', CRONTAB_ROOT . '/yii ');

class System
{
    public static function getCpu()
    {
        if (file_exists('/proc/cpuinfo')) {
            $info = shell_exec("cat /proc/cpuinfo");

            return substr_count($info, "processor");
        }

        return 1;
    }

    /**
     * 获取进程 id
     *
     * @param string $script
     * @return mixed
     */
    public static function getProcessId($script)
    {
        exec(self::_getProcess($script) . " | awk '{print $2}'", $output);

        return $output;
    }

    /**
     * 获取执行脚本
     *
     * @param string $script
     * @return mixed
     */
    public static function getProcessInfo($script)
    {
        exec(self::_getProcess($script) . " | awk '{print $9}'", $output);

        return $output;
    }

    /**
     * 获取进程数量
     *
     * @param $script
     * @return int
     */
    public static function getProcessNum($script)
    {
        $command = self::_getProcess($script) . " | awk '{count++}END{print count}'";

        return intval(shell_exec($command));
    }

    /**
     * 查询进程信息 shell 命令
     *
     * @param string $script
     *
     * @return string
     */
    private static function _getProcess($script)
    {
        $command = "ps -ef | grep -E '" . BIN_PATH . "{$script}$' | grep -v grep";

        return $command;
    }

    public static function stop($script)
    {
        $ids = self::getProcessId($script);
        $status = true;

        if ($ids && is_array($ids)) {
            foreach ($ids as $id) {
                if (self::stopByid($id)) {

                } else {
                    $status = false;
                    break;
                }
            }
        }

        return $status;
    }

    /**
     * 停止进程
     *
     * 现在是直接强杀的
     *
     * @param $id
     * @return bool
     */
    public static function stopByid($id)
    {
        exec("kill -9 $id", $output, $status);

        return $status === 0 ? true : false;
    }

    public static function start($bin, $script, $num = 1, $log = '/dev/null', $sudo = 'sudo -u nobody ')
    {
        $current = self::getProcessNum($script);
        $num -= $current;
        if ($num > 0) {
            $command = "{$sudo} " . BIN_PATH . "{$script} >> $log &";
            for ($i = 0; $i < $num; $i++) {
                exec($command, $output, $status);
                if ($status !== 0) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function startByRoot($bin, $script, $num = 1, $log = '/dev/null')
    {
        return self::start(BIN_PATH, $script, $num, $log, '');
    }

    public static function exists($script)
    {
        return self::getProcessNum($script) > 1;
    }

    public static function getServerIp()
    {
        exec("/sbin/ifconfig | awk '/inet addr/{print $2}' | awk -F: '{print $2}'", $output);

        return $output;
    }
}
