<?php

/**
 * 患者注册环信用户
 */
class task_usersregister extends task_processor {

    public function run($parameter, $code) {
        $userid = isset($parameter['userid']) ? $parameter['userid'] : 0;
        $prefix = isset($parameter['prefix']) ? $parameter['prefix'] : 0;
        
        if($userid < 1) {
            $this->logs('userregister', 'ing', $code, 'faild:params error:userid:' . $userid);
        }
        
        $hxuser = HuanxinUserHelper::regUserInfo($userid, $prefix);
        if ($hxuser) {
            $this->logs('userregister', 'ing', $code, 'succ:userid:' . $userid . ":hxuser:" . json_encode($hxuser));
            return 'succ:userid:' . $userid . ":hxuser:" . json_encode($hxuser);
        } else {
            $this->logs('userregister', 'ing', $code, 'faild:userid:' . $userid);
            return 'faild:userid:' . $userid;
        }
    }

}
