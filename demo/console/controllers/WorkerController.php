<?php
namespace console\controllers;
use \tanshuimaoxian\crontab\Task;
use \tanshuimaoxian\crontab\System;

class WorkerController extends Task
{
    public function actionRun()
  	{
  		while (true) {
  			$msg = date("Y-m-d H:i:s")."\n";
  			$this->log($msg);
  			$this->sleep(10);
  		}
  	}
}