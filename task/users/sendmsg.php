<?php

/**
 * 用户之间互相发送消息
 * cuikai 16-5-31 下午5:31
 */

class task_userssendmsg extends task_processor {

    public function run($parameter, $code) {
        $from   = isset($parameter['from']) ? $parameter['from'] : '';
        $to     = isset($parameter['to']) ? $parameter['to'] : '';
        $msg    = isset($parameter['msg']) ? $parameter['msg'] : '';
        
        // =============================================================
        // 区域医疗自定义数据
        // $ext['fromRealName'] = '患者01';  //患者姓名
        // $ext['fromAvatar'] 	= 'http://aa.jpg'; //患者头像
        // $ext['toRealName'] 	= '我'; // 这个参数暂不明确，等待app确认
        // =============================================================
        $ext = isset($parameter['ext']) ? $parameter['ext'] : '';
        
        if($from == '' || $to=='' || $msg == '' || empty($ext)) {
            $this->logs('userssendmsg', 'ing', $code, 'failed:params error:' . json_encode($parameter));
            return 'userssendmsg:failed:params error';
        }
        
        $res = HuanxinUserHelper::setTxtMessage($from, $to, $msg, $ext);
        
        if($res) {
            $this->logs('userssendmsg', 'ing', $code, 'succ:params:' . json_encode($parameter));
            return 'succ:params:' . json_encode($parameter);
        }else{
            $this->logs('userssendmsg', 'ing', $code, 'failed:params:' . json_encode($parameter));
            return 'failed:params:' . json_encode($parameter);
        }
    }
}