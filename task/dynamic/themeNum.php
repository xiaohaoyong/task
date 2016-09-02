<?php

/**
 * 话题关联动态数推送处理
 * User: xywy
 * Date: 2016/5/50
 * Time: 16:21
 */
class task_dynamicthemeNum extends task_processor
{
    private $themeid; //话题id
    private $userid; //动态id
    private $userids; //推送用户数组
    private $code; //任务序号

    //任务处理执行方法
    public function run($parameter, $code)
    {
        if ($parameter['themeid'] && $parameter['userid']) {
            $this->code = $code;
            $this->themeid = intval($parameter['themeid']);
            $this->userid = intval($parameter['userid']);
            $this->pushallusers();
            foreach ($this->userids as $k => $v) {
                if($v!=$this->userid) {
                    $this->pushtheme($v,$this->themeid);
                }

            }
            return "成功，发送完成总数：" . count($this->userids);
        } else {
            return "失败，缺少参数";
        }

    }

    /**
     * 获取全部用户
     */
    private function pushallusers()
    {
        include __STATIC__ . "/location.php";
        $A = new Activeuser();
        //全部登录的用户
        $alluser = $A->get_all();
        //创建话题全部用户
        $dbs = Register::get('dbsdc');
        $sql = "select userid from dc_theme where id=? and level>0";
        $theme_userid = $dbs->GetRow($sql, array($this->themeid));
        $users1 = $theme_userid?$theme_userid:array();
        //话题关联全部用户
        $sql = 'select userid from dc_theme_dynamic where themeid=? and level>0 group by userid';
        $dynamic_userid = $dbs->GetAll($sql, array($this->themeid));
        $users2 = $dynamic_userid?array_map('array_pop', $dynamic_userid):array();
        //全部话题粉丝用户
        $sql = "select userid from dc_theme_fans where themeid=?";
        $fans_userid = $dbs->GetAll($sql, array($this->themeid));
        $users3 =$fans_userid? array_map('array_pop', $fans_userid):array();
        $redis = Register::get('rdmp');
        //首页推荐三个话题的相关用户
        $userlist=$redis->HGETALL('dc:theme:recommend:user');
        if($userlist) {
            foreach ($userlist as $ks => $vs) {
                //推荐的三个话题
                $arrt = explode('-|-', $vs);
                //三个推荐话题的id转换成数组
                $themeid_arr=explode(',',$arrt[0]);
                //推送中取得三个话题的id字符串
               $lists=$redis->HGET('dc:theme:recommend:',$arrt[1]);
                $rocommend= $lists?explode('-|-', $lists):'';
                //判断关联话题的id是否在，判断推荐中的id和用户存储中的id是否一致
                if (in_array($this->themeid, $themeid_arr) &&  $rocommend[0]==$arrt[0]) {
                    $users4[] = $ks;
                }
            }
        }
        $users4?$users4:$users4=array();
        //将要推送的全部用户去重
        $userids_All = array_flip(array_flip(array_merge($users1, $users2, $users3,$users4)));
        //登录医脉用户和推送用户取交集
        $this->userids = array_intersect($userids_All, $alluser);
        //测试
        // $this->userids= array(87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122);

    }

    private function pushtheme($userid,$themeid)

    {
        $redis = Register::get('rdmp');
        $msg=$redis->zIncrBy('dc:report:dynamic:num' . $userid, 1, $themeid);
        $msg=$msg?"success":"fail";
        $log=$this->themeid.'|@@'.$userid."|@@".$msg;
        $this->logs('dynamicthemeNum','ing',$this->code,$log);

    }

}