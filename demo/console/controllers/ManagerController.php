<?php
namespace console\controllers;
use \tanshuimaoxian\crontab\Task;
use \tanshuimaoxian\crontab\System;

class ManagerController extends Task
{
    protected $tasks = array();
    public function actionRun()
    {
        $mtask = 'manager/run';
        if (System::exists($mtask)) {
            exit();
        }

        $this->tasks = \Yii::$app->params['tasks'];
        try {
            $this->stop();
            while(true) {
                $this->check();
                sleep(60);
            }
        } catch(Exception $e) {
            $msg = $e->getMessage() . "\n";
            $this->log($msg);
            exit();
        }    
    }

    public function actionStop()
    {
        $this->tasks = \Yii::$app->params['tasks'];
        try {
            $this->stop();
        } catch(Exception $e) {
            $msg = $e->getMessage() . "\n";
            $this->log($msg);
            exit();
        }  
    }

    protected function stop() 
    {
        if (empty($this->tasks)){
            return true;
        }
        foreach ($this->tasks as $item) {
            $task = $item[0];
            if (!System::exists($task)) {
                continue;
            }
            $this->log("stop {$task}");
            if (!System::stop($task)) {
                throw new Exception("stop {$task} error");
            } 
        }
    }

    protected function check() 
    {
        if (empty($this->tasks)){
            return true;
        }
        foreach ($this->tasks as $key => $item) {
            $task = $item[0];
            $num = $item[1];
            $runType = $item[2];
            $state = $item[3];
            if (!$state || ($runType && !IS_MASTER)) {
                unset($this->tasks[$key]);
                continue;
            }
            $current = System::getProcessNum($task);
            $num-= $current;
            if ($num > 0) {
                $msg = sprintf("[%s] %s ", date('Y-m-d H:i:s'), "start {$task}");
                if (System::start(CRONTAB_ROOT.'/yii', $task, $num, '/dev/null', '')) {
                    $this->log("start {$msg} ok");
                } else {
                    $this->log("start {$msg} fail");
                }
            }
        }
    }
}