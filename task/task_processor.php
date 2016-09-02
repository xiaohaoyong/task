<?php
/**
 * 任务队列处理器
 * 用于处理全部的任务
 * 执行方式 task_processor.php 项目 操作 如task_processor.php media push
 * 获取分发器分发的任务 任务名称=项目+操作
 * 执行方式：从队列中获取到任务参数，指定任务处理类实例化后执行
 *
 * 汪振 2016-03-31
 */
ini_set('default_socket_timeout', -1);  //不超时

$dir = dirname(dirname(dirname(__FILE__)));
include $dir . '/global.php';
include $dir . '/app/1.2/doctorcircle/dcMsg.class.php';
ini_set('memory_limit','1024M');
ini_set("max_execution_time", "18000");
$m=$argv[1]?$argv[1]:$_GET['m'];
$a=$argv[2]?$argv[2]:$_GET['a'];
if($m && $a) {

    $type=$m.$a;
    $key="yimai:list:task:".$type;
    $redis=Register::get('rdtask');
    $totle=$redis->lLen($key);
    if($totle>0)
    {
        //获取任务执行类文件
        include $m . "/" . $a . ".php";
        $className = 'task_' . $type;

        $task=new task_processor();
        for($i=1;$i<=$totle;$i++)
        {
            //获取任务参数
            $parameter=$redis->lPop($key);
            //任务序号
            $code=time();
            //任务日志记录任务开始以及参数内容
            $task->logs($type,'end',$code,$parameter);
            $object = new $className();
            $parameter=json_decode($parameter,true);
            $return=$object -> run($parameter,$code);
            //记录任务成功失败
            $task->logs($type,'end',$code,$return);
        }
    }
}
