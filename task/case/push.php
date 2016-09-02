<?php
/**
 * 公众号发布病历研讨班
 * 处理流程 根据关注用户的类型获取该类型下全部用户，添加医生关注关系，添加环信关注关系
 *
 * 汪振2016-03-31
 */
class task_casepush extends task_processor
{
    private $subid; //公众号序号
    private $seminarid;//病例id
    private $code; //任务序号
    private $parameter; //参数
    private $ext; //消息模版
    private $msgBody;//消息体
    //任务处理执行方法
    public function run($parameter,$code)
    {
        $this->parameter=$parameter;
        if($parameter['subid'] && $parameter['seminarid'])
        {
            $this->code=$code;
            $this->subid  =intval($parameter['subid']);
            $this->seminarid  =intval($parameter['seminarid']);
            $users=$this->pushusers();
            if($users){
                $stime=microtime(true); //获取程序开始执行的时间
                $this->subRow()->seminarRow()->set_ext();
                foreach($users as $k=>$v) {
                    $this->send($v);
                }

                $dbi=Register::get('dbidc');
                $datasminar['state'] = 2;
                $rs = $dbi->Upd('dc_dynamic_seminar', $datasminar, 'dynamicid=?', array($this->seminarid));

                $etime=microtime(true);//获取程序执行结束的时间
                $total=$etime-$stime;   //计算差值
                return "发布成功，总数：".count($users).",用时：$total";
            }else{
                return "失败,无关注用户";

            }
        }else{
            return "失败，缺少参数";
        }
    }
    private function subRow()
    {
        $sql = "select * from subscription where id=?";
        $dbsud = Register::get('dbsud');
        $this->userinfo = $dbsud->GetRow($sql, array($this->subid));
        return $this;
    }

    /**
     * 获取发送内容
     */
    private function seminarRow()
    {
        $dbs=Register::get('dbsdc');



        $sql = "select * from dc_dynamic_seminar where dynamicid=?";
        $dynamic = $dbs->GetRow($sql, array($this->seminarid));
        if ($dynamic) {
            $this->msgBody['dynamicid'] = $this->seminarid;


            if ($dynamic['end_state'] == 1) {
                $seminar = array('answer' => '答案');
                $title = $dynamic['phase'].' —— '.$this->userinfo['name'].'答案揭晓';
            } else {
                $seminar = array('complain' => '主诉', 'now_history' => '现病史', 'past_history' => '过往病史', 'physical' => '体格检查', 'sup_exa' => '辅助检查', 'diagnosis' => '诊断', 'basis' => '诊断依据', 'further_exa' => '进一步检查', 'treatment' => '治疗');
                $title = $dynamic['phase'].' —— '.$dynamic['title'];
            }

            $this->msgBody['title'] = $title ? $title : "";

            $content = "";
            foreach ($seminar as $k => $v) {
                if ($dynamic[$k]) {
                    $content .= "[".$v."]：".$dynamic[$k]."\n";
                }
            }
            $content = mb_substr($content, 0, 250, 'utf-8');
            $this->msgBody['countent'] = $content ? $content : "";

            //获取动态图片
            $tm = $this->seminarid % 50;
            $sql = "select src from dc_dynamic_img_{$tm} where dynamicid=? order by id desc limit 1";
            $image = $dbs->GetOne($sql, array($this->seminarid));
            $this->msgBody['type'] = $image ? 1 : 0;
            $this->msgBody['imageUrl'] = $image ? $image : "";
        }

        return $this;
    }

    /**
     * 获取关注用户
     */
    //获取需要推送的用户
    private function pushusers()
    {
        if($this->users){
            return $this->users;
        }
        $dbs=Register::get('dbsclub');
        $sql="select uid from share_focus where gz_uid=? group by uid";
        $funs=$dbs->GetAll($sql,array($this->subid));
        $list = array_map('array_pop',$funs);
        return $list;
    }
    /**
     * 设置发送模版
     */
    private function set_ext()
    {
        $this->ext = array(
            'fromRealName' => $this->userinfo['name'],
            'fromAvatar' => $this->userinfo['img'],
            'toRealName' => '我',
            'toAvatar' => $this->userinfo['img'],
            'newMsgType' => 'seminar',
            'em_ignore_notification' => false,
            'msgBody' =>$this->msgBody
        );
        return $this->ext;
    }
    /**
     * 是否静默发送
     */
    private function ignore_notification($userid)
    {
        $redis=Register::get('rdmp');
        //判断是否静默发送
        if ($redis->SISMEMBER('mp:blacklist:userid:' . $userid, $this->subid)) {
            $this->ext["em_ignore_notification"] = true;
        } else {
            $this->ext["em_ignore_notification"] = false;
        }
    }
    /**
     * 发送消息
     */
    private function send($userid)
    {
        $this->ignore_notification($userid);
        $seminarHelper = new seminarHelper();
        $return = $seminarHelper->seminarMessagetest($this->subid, $userid, $this->ext,$this->msgBody['title']);
        $logs[]=$this->subid;
        $logs[]=$this->seminarid;
        $logs[]=$userid;
        $logs[]=json_encode($return);
        $ext=$this->ext;
        $ext['msgBody']['countent']=mb_substr($ext['msgBody']['countent'], 0, 10, 'utf-8');
        $logs[]=json_encode($ext);
        $log=implode('|@@',$logs);
        $this->logs('casepush','ing',$this->code,$log);
    }
}


