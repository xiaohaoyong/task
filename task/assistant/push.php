<?php
/**
* 我的助理
*/
class task_assistantpush extends task_processor
{
	private $_users = array();//推送用户组
	private $_msg;//推送内容
	private $_parameter;//参数数组
	private $_code;//任务序号
	private $_type;//用户
	
	public function run($parameter,$code)
	{
		$this->_parameter = $parameter;
		$this->_code = $code;
		if($parameter['msg'] && $parameter['type'])
		{
			$this->_msg = trim(htmlspecialchars($parameter['msg']));
			$this->_type = trim(htmlspecialchars($parameter['type']));

			if($parameter['type'] == "UserIdByOneself")
			{
				$this->_users = explode(',',$parameter['userid']);//自定义userid
			}else{
				$this->_users = $this->$parameter['type']();
			}			

			$data = $this->_send();
			//成功
			$success_total = count($data['success']);
			$success_str = "成功，发送内容：“".$this->_msg."”，总数：".$success_total;
			//失败
			$fail_total = count($data['fail']);
			$fail_str = "失败，总数：".$fail_total;
			return $success_str."!==!".$fail_str;
		}else{
			return "失败，缺少参数";
		}
	}

	//测试环境测试用户
	private function _test_test_user()
	{
		return array(68137476,87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122,68245669);
	}

	//真实环境测试用户
	private function _test_user()
	{
		return array(92760501,87952241,61046948,68233710,19088510,18732252,68233714,54131174,9345330,44069973,57266322,57638620,65173122,68245669);
	}

	//推送所有用户
	private function _all_user()
	{
		$Au=new Activeuser();
		$list=$Au->get_all();
		return array_unique($list);
	}

	private function _liver_user()
	{
		$redis=Register::get('rdliver');
		return $redis->sMembers(DOCTORACTIVEALL);
	}

	//发送消息
	private function _send()
	{
		$userAssistant = new seminarHelper();
		$from = "doctor_assistant";
        $ext = array('fromRealName' => "我的助理", 'fromAvatar' => 'http://static.img.xywy.com/club/ypt_app/assistant.png', 'toRealName' => '我',);
        $data = array();
		$no = array();
		foreach ($this->_users as $key => $val) 
		{
			$result = $userAssistant->seminarMessage($from, $val, $ext,$this->_msg);
			if($result)
			{
				$data[] = $val;
				$this->logs('assistantpush','ing',$this->_code,implode('|@@', array($val)).'|@@成功');
			}else{
				$no[] = $val;
				$this->logs('assistantpush','ing',$this->_code,implode('|@@', array($val)).'|@@失败');
			}
		}

		return array('success'=>$data,'fail'=>$no);
	}
}
?>