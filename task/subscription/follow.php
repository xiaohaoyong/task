<?php
/**
 * 公众号默认关注用户处理
 * 处理流程 根据关注用户的类型获取该类型下全部用户，添加医生关注关系，添加环信关注关系
 *
 * 汪振2016-03-31
 */
class task_subscriptionfollow extends task_processor
{
    private $subid; //公众号序号
    private $code; //任务序号
    private $parameter; //参数
    //任务处理执行方法
    public function run($parameter,$code)
    {
        $this->parameter=$parameter;
        if($parameter['subid'] && $parameter['type'])
        {
            $this->code=$code;
            $this->subid  =intval($parameter['subid']);
            $list=self::$parameter['type']();
            if(count($list)>0){

                foreach($list as $k=>$v)
                {
                    $this->addfollow($v);
                }
                return "成功，关注用户数：".count($list);
            }
            return "失败,未定义的组用户";

        }else{
            return "失败，缺少参数";
        }
    }
    function __call($function_name, $args)
    {
        return array();
    }
    //科室用户
    private  function subject()
    {
        $subjectid=$this->parameter['subjectid'];
        $redis=Register::get('rdmp');
        $list=$redis->sMembers(CACHEKEY_DC_OFFICE.$subjectid);
        return $list;
    }
    //全部用户
    private  function alluser()
    {
        $Au=new Activeuser();
        $list=$Au->get_all();
        return $list;
    }
    //全部肝友会用户
    private  function liverall()
    {
        $Au=new Activeuser();
        $list=$Au->get_liver_all();
        return $list;
    }
    //测试环境测试用户
    private  function testtestuser()
    {
        return array(87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122,68245783,52625185,20751339,68248259,68249009);
    }
    //真实环境测试用户
    private  function testuser()
    {
        return array(87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122,91010307);
    }
    private function addfollow($userid)
    {
        $dbiclub=Register::get('dbiclub');
        $sql="select count(*) from share_focus where uid=? and gz_uid=?";
        $share=$dbiclub->GetOne($sql,array($userid,$this->subid));
        if(!$share) {
            $data['uid'] = $userid;
            $data['time'] = date('Y-m-d H:i:s');
            $data['gz_uid'] = $this->subid;
            $model = new model($dbiclub);
            $model->tablename = 'share_focus';
            $rs = $model->AutoExecute($data);

            if ($rs) {
                $redis = Register::get(MPDCREDIS);
                $redis->zAdd(CACHEKEY_DC_FL . $this->subid, time(), $userid);
            }
            //加入我的关注缓存列表
            $redis = Register::get(MPDCREDIS);
            $redis->zAdd(CACHEKEY_DC_WF . $userid, time(), $this->subid);

            $dbsud = Register::get('dbsud');
            $id = $dbsud->GetOne('select id from subscription where id=?', array($this->subid));
            if ($id) {
                $redis = Register::get('rdmp');
                $redis->zadd(CACHEKEY_USER_S . $userid, time(), $this->subid);
                //添加环信好友关系
                $return = HuanxinUserHelper::addRelation('sid_' . $this->subid, 'did_' . $userid);
            }

            $returnarr[] = $userid;
            $returnarr[] = $this->subid;
            $returnarr[] = implode('@', $return);
            $this->logs('subscriptionfollow','ing',$this->code,implode('|@@', $returnarr));
        }
    }

}


