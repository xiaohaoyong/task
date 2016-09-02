<?php
/**
 * 医圈动态推送处理
 * User: xywy
 * Date: 2016/4/27
 * Time: 16:21
 */
class task_dynamicpush extends task_processor
{
    private $dynamicid; //动态id
    private $userid; //用户id
    private $userids; //推送用户数组
    private $code; //任务序号
    //任务处理执行方法
    public function run($parameter,$code)
    {
        if($parameter['dynamicid'] && ($parameter['userid'] || $parameter['type']))
        {
            $this->code=$code;
            $this->dynamicid  =intval($parameter['dynamicid']);
            $this->userid     =intval($parameter['userid']);
            if($parameter['type'])
            {
                $this->$parameter['type']();
            }elseif($this->userid){
                $this->pushusers();
            }else{
                return "失败，缺少参数";
            }
            $redis=Register::get('rdmp');
            foreach($this->userids as $k=>$v)
            {
                if($k!=$this->userid) {
                    $this->pustDynamic($k, $v);
                }
            }
            return "成功，发送完成总数：".count($this->userids);
        }else{
            return "失败，缺少参数";
        }
    }

    /**
     * 获取全部用户
     */
    private function pushallusers()
    {
        $A=new Activeuser();
        $alluser=$A->get_all();
        $array = array_fill(0, count($alluser), '编辑推荐');
        $userlist1=array_combine($alluser,$array);
        $this->userids=$userlist1;
    }
    //获取需要推送的用户
    private function pushusers()
    {
        include __STATIC__."/location.php";
        $A=new Activeuser();
        $alluser=$A->get_all();

        //判断发布动态用户类型 :普通好友，同科室，同医院，同地区。
        $mem=new MyMemcache();
        $redis=Register::get('rdmp');
        $dbsclub=Register::get('dbsclub');

        $userlist1=$userlist2=$userlist3=$userlist4=array();
        $docRow=$mem->get_doctor($this->userid,'utf-8');

        if($docRow) {
            $array=array();
            if ($docRow['isdoctor'] == 12 || $docRow['isdoctor'] == 13 || $docRow['isdoctor'] == 14) {
                $sql="select pid from user_doctor_new where isdoctor in (12,13,14)";
                $list=$dbsclub->GetAll($sql);
                $users1=array_map('array_pop',$list);
                $users1=array_intersect($users1,$alluser);
                $array = array_fill(0, count($users1), 0);
                $userlist1=array_combine($users1,$array);

            }else{
                $profess_job=array('医生','医师');
                if(in_array($docRow['profess_job'],$profess_job)){
                    //获取同科室
                    $sql="select pid from user_doctor_new where subject=?";
                    $list=$dbsclub->GetAll($sql,array($docRow['subject']));
                    $users1=array_map('array_pop',$list);
                    $users1=array_intersect($users1,$alluser);
                    $array = array_fill(0, count($users1), '同科室');
                    $userlist1=array_combine($users1,$array);
                }else{
                    $sql="select pid from user_doctor_new where profess_job=?";
                    $list=$dbsclub->GetAll($sql,array(iconv('utf-8','gbk',$docRow['profess_job'])));
                    $users1=array_map('array_pop',$list);
                    $users1=array_intersect($users1,$alluser);
                    $array = array_fill(0, count($users1),'同执业');
                    $userlist1=array_combine($users1,$array);
                }
            }

            if($docRow['hospital']) {
                //获取同医院
                $array = array();
                $sql = "select pid from user_doctor_new where hospital=?";
                $list = $dbsclub->GetAll($sql, array(iconv('utf-8','gbk',$docRow['hospital'])));
                $users2 = array_map('array_pop', $list);
                $users2 = array_intersect($users2, $alluser);
                $array = array_fill(0, count($users2), '同医院');
                $userlist2 = array_combine($users2, $array);
            }
            //获取同地区
            if($docRow['province']) {
                $array=array();
                $sql = "select pid from user_doctor_new where province=?";
                $list = $dbsclub->GetAll($sql, array($docRow['province']));
                $users3 = array_map('array_pop', $list);
                $users3 = array_intersect($users3, $alluser);
                $array = array_fill(0, count($users3), $array_location[$docRow['province']]);
                $userlist3 = array_combine($users3, $array);
            }
        }
        //获取好友
        $array=array();
        $sql="select uid from share_focus where gz_uid=?";
        $funs=$dbsclub->GetAll($sql,array($this->userid));
        $list = array_map('array_pop',$funs);
        $array = array_fill(0, count($list), 0);
        $userlist4=array_combine($list,$array);
        //获取15天内的活跃用户
        $user5=$redis->ZRANGE('dc:activity:user',0,-1);
        $array = array_fill(0, count($user5), 0);
        $userlist5=array_combine($user5,$array);
        $userlist1=$userlist1?$userlist1:array();
        $userlist2=$userlist2?$userlist2:array();
        $userlist3=$userlist3?$userlist3:array();
        $userlist4=$userlist4?$userlist4:array();
        $userlist5=$userlist5?$userlist5:array();
        $users=$userlist1+$userlist2+$userlist3+$userlist4+$userlist5;
        $this->userids=$users;
    }

    private function pustDynamic($userid,$msg)
    {

        if($this->userid==96723560 || $this->userid==98216833){
            $redis=Register::get('rdliver');
        }else{
            $redis=Register::get('rdmp');
        }
        $time=time();
        if(!$redis->zScore(CACHEKEY_DC_DL.$userid,$this->dynamicid)) {
            $redis->zAdd(CACHEKEY_DC_DL . $userid, $time,$this->dynamicid);
            $redis->ZREMRANGEBYRANK(CACHEKEY_DC_DL . $userid,0,-200);
        }
        $redis->hset(CACHEKEY_DC_DS . $userid, $this->dynamicid,$msg );
        $log=$this->dynamicid.'|@@'.$userid."|@@".$msg;
        $this->logs('dynamicpush','ing',$this->code,$log);
    }

}