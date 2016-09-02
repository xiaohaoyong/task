<?php
/**
 * 任务队列分发器
 * 全部任务将提交到yimai:list:task 队列中，在此脚本中将任务按照任务类型分发至具体处理的任务队列中
 * 此队列中的value格式为：任务类型|@任务参数(json)
 * 实例mediapush|@{'id'=>123,'zxid'=>123} 媒体号push消息队列
 *
 * 汪振 2016-03-31
 */


$dir = dirname(dirname(dirname(__FILE__)));
include $dir . '/global.php';
$redis=Register::get('rdtask');
$key="yimai:list:task";
$totle=$redis->lLen($key);
var_dump($totle);
for($i=0;$i<$totle;$i++)
{
    $value=$redis->lPop($key);
    $str=explode('|@',$value);
    $class = $str[0];
    $redis->rpush($key.":".$class,$str[1]);
    file_put_contents(LOG_PATH.'yimai_task_distributor.log',date('Y-m-d H:i:s').$value."\n",FILE_APPEND);
    echo date('Y-m-d H:i:s').$value."\n";
}
echo "============end";

//任务类型，参数
//$value="mediapush|@{'id'=>123,'zxid'=>123}";


/*

$parameter=json_decode($st[1],true);
include $class."/".$class.".php";
$object=new $class($parameter);
$object->run($parameter);
*/