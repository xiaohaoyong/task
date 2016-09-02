<?php

/**
 * 发布资讯 [ 病历研讨班 ]
 *
 * cuikai 16-5-31 下午2:14 
 */
class task_casezixun extends task_processor {

    private $subid; //公众号序号
    private $seminarid; //病例id
    private $code; //任务序号
    private $parameter; //参数
    private $ext; //消息模版
    private $msgBody; //消息体

    //任务处理执行方法

    public function run($parameter, $code) {
        $this->parameter = $parameter;
        if ($parameter['subid'] && $parameter['seminarid']) {
            $this->code = $code;
            $this->subid = intval($parameter['subid']);
            $this->seminarid = intval($parameter['seminarid']);
            $users = $this->pushusers();
            if ($users) {
                $stime = microtime(true); //获取程序开始执行的时间
                $this->subRow()->seminarRow()->set_ext();
                foreach ($users as $k => $v) {
                    $this->send($v);
                }

                $dbi = Register::get('dbiud');
                $datasminar['state'] = 2;
                $datasminar['end_state'] = 1;
                $rs = $dbi->Upd('dc_dynamic_zixun', $datasminar, 'dynamicid=?', array($this->seminarid));

                $etime = microtime(true); //获取程序执行结束的时间
                $total = $etime - $stime;   //计算差值
                return "发布成功，总数：" . count($users) . ",用时：$total";
            } else {
                return "失败,无关注用户";
            }
        } else {
            return "失败，缺少参数";
        }
    }

    private function subRow() {
        $sql = "select * from subscription where id=?";
        $dbsud = Register::get('dbsud');
        $this->userinfo = $dbsud->GetRow($sql, array($this->subid));
        return $this;
    }

    /**
     * 获取发送内容
     */
    private function seminarRow() {
        $dbs = Register::get('dbsdc');
        $dbsud = Register::get('dbsud'); //医平台库
        $dbszx = Register::get('dbszx'); //咨询库

        $sql = "select * from dc_dynamic_zixun where dynamicid=?";
        $dynamic = $dbsud->GetRow($sql, array($this->seminarid));
        
        $sql = "select model,content,vector,catpid,catid from info_article where id=?";
        $article_info = $dbszx->GetRow($sql, array($dynamic['zixunid']));       
                
        $this->msgBody['title'] = $dynamic['phase'] . ' —— ' . $dynamic['title'];//消息标题
        //$this->msgBody['vector'] = $article_info['vector'];//咨询导读        
        $this->msgBody['countent'] = $dynamic['intro'];//咨询简介
        //$this->msgBody['dynamicid'] = $this->seminarid; //动态id
        $this->msgBody['zixunid'] = $dynamic['zixunid']; //咨询id       
        
        /**
         * 来源地址 && 分享链接
         * source 视频源为2，普通资讯为1
         * share_link 视频源时展示资讯表info_article表中的content字段，普通资讯自己拼地址
         */
        $countid = $article_info['catpid'] == 1 ? $article_info['catid'] : $article_info['catpid']; //统计标识
        if (in_array($article_info['model'], array(0, 1))) {
            $this->msgBody['source'] = 1;
            $this->msgBody['link'] = __ZIXUNURL__ . "/d" . $dynamic['zixunid'] . ".html?xywyfrom=h5&cat={$countid}";            
        } else if (in_array($article_info['model'], array(4))) {
            $this->msgBody['source'] = 2;
            $this->msgBody['link'] = __ZIXUNURL__ . "/d" . $dynamic['zixunid'] . ".html?xywyfrom=h5&cat={$countid}";
            $videos = explode('|', $article_info['content']);
            $this->msgBody['uu'] = !empty($videos[0]) ? $videos[0] : '';
            $this->msgBody['vu'] = !empty($videos[1]) ? $videos[1] : '';
        }
        
        //获取动态图片
        $tm = $this->seminarid % 50;
        $sql = "select src from dc_dynamic_img_{$tm} where dynamicid=? order by id desc limit 1";
        $image = $dbs->GetOne($sql, array($this->seminarid));
        //消息类型 0普通消息, 1图片消息
        $this->msgBody['type'] = 0;
        if ($image) {
            $this->msgBody['type'] = 1;
        }
        $this->msgBody['imageUrl'] = $image ? $image : "";

        return $this;
    }

    //获取需要推送的用户
    private function pushusers() {
        if ($this->users) {
            return $this->users;
        }
        $dbs = Register::get('dbsclub');
        $sql = "select uid from share_focus where gz_uid=? group by uid";
        $funs = $dbs->GetAll($sql, array($this->subid));
        $list = array_map('array_pop', $funs);
        return $list;
    }

    /**
     * 设置发送模版
     */
    private function set_ext() {
        $this->ext = array(
            'fromRealName' => $this->userinfo['name'],
            'fromAvatar' => $this->userinfo['img'],
            'toRealName' => '我',
            'toAvatar' => $this->userinfo['img'],
            'newMsgType' => 'zixun',
            'em_ignore_notification' => false,
            'msgBody' => $this->msgBody
        );
        return $this->ext;
    }

    /**
     * 是否静默发送
     */
    private function ignore_notification($userid) {
        $redis = Register::get('rdmp');
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
    private function send($userid) {
        $this->ignore_notification($userid);
        $seminarHelper = new seminarHelper();
        $return = $seminarHelper->seminarMessage($this->subid, $userid, $this->ext, $this->msgBody['title']);
        $logs[] = $this->subid;
        $logs[] = $this->seminarid;
        $logs[] = $userid;
        $logs[] = $return ? "success" : "fail";
        $ext = $this->ext;
        $ext['msgBody']['countent'] = mb_substr($ext['msgBody']['countent'], 0, 10, 'utf-8');
        $logs[] = json_encode($ext);
        $log = implode('|@@', $logs);
        $this->logs('casezixun', 'ing', $this->code, $log);
    }

}
