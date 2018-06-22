<?php
namespace console\controllers;
use \tanshuimaoxian\crontab\Task;
use \tanshuimaoxian\crontab\System;

class MainController extends Task
{
  public function actionRun()
  {
      $task = 'manager/run';
      $num = System::getProcessNum($task);
      if ($num > 0) {
         return true;
      }
      while ($this->try < $this->tryLimit) {
        $this->try++;
        $status = System::startByRoot(CRONTAB_ROOT.'/yii', $task, 1);
        if ($status) {
            $this->log("start {$task}");
            break;
        }
      }
  }

  public function actionStop()
  {
      $task = 'manager/run';
      $num = System::getProcessNum($task);
      if ($num > 0) {
          while (true) {
              if (System::stop($task)) {
                   $this->log("stop {$task}");
                  break;
              }
              sleep(5);
          }
      }
      $task = 'manager/stop';
      $num = System::getProcessNum($task);
      if ($num > 0) {
          return true;
      }
      System::startByRoot(CRONTAB_ROOT.'/yii', $task, 1);
  }
}