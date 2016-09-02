<?php

/**
 * 话题推送处理
 * User: xywy
 * Date: 2016/4/27
 * Time: 16:21
 */
class task_dynamicthemepush extends task_processor
{
    private $themeid; //动态id
    private $title; //推送内容
    private $userids; //推送用户数组
    private $sendVal;//需要推送的内容
    private $code; //任务序号

    //任务处理执行方法
    public function run($parameter, $code)
    {
        if ($parameter['themeid']) {
            $this->code = $code;
            $this->themeid = intval($parameter['themeid']);
            $this->title = $parameter['title'];
            $this->pushallusers();
            if ($this->title) {
                $this->sendValue();
            } else {
                $this->themeInfo()->sendValue();
            }
            //$this->pushallusers();
            $this->pushtheme();

            return "成功，发送完成";
        } else {
            return "失败，缺少参数";
        }
    }

    /**
     * 获取全部用户
     */
    private function pushallusers()
    {
        $A = new Activeuser();
        $alluser = $A->get_all();

        //测试环境测试用户
        //$alluser= array(87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122);
        $this->userids = $alluser;
    }

    //获取需要发送的话题
    private function themeInfo()
    {
        $dbs = Register::get('dbsdc');
        $sql = "select theme from dc_theme where id=?";
        $actRow = $dbs->GetRow($sql, array($this->themeid));

        $this->title = $actRow['theme'];
        return $this;
    }

    //组合需要发送的push参数
    private function sendValue()
    {
        $json['t'] = 6;
        $json['c'] = $this->themeid;
        $post_fields = urlencode(serialize($json));

        $value['message'] = $post_fields;
        $value['content'] = $this->title;
        $this->sendVal = $value;
    }

    private function pushtheme($userid = 1)
    {

        $sign = md5("d4739c4203d4aea80e95$userid");
        $this->sendVal['uid'] = $userid;
        $url = __CLUBAPIURL__ . "/push/push.interface.php?sign=" . $sign."&type=1&registration_id=all";
        $curl = new Curl();
        $return = $curl->post($url, $this->sendVal);
        $r = strpos($return, 'success') !== false ? "success" : "fail";
        $this->logs('dynamicthemepush', 'ing', $this->code, $this->themeid . "|@@" . implode('|@@', $this->sendVal) . "|@@" . $r);
    }

}