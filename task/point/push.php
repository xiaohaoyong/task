<?php
/**
* 添加积分
*/
class task_pointpush extends task_processor
{
	private $_parameter;//参数数组
	private $_code;//任务序号

	public function run($parameter,$code)
	{
		$this->_parameter = $parameter;
		$this->_code = $code;
		if(!empty($this->_parameter))
		{
			$data = $this->_addPoint();
			//成功
			$success_total = count($data['success']);
			$success_str = "成功，总数：".$success_total;
			//失败
			$fail_total = count($data['fail']);
			$fail_str = "失败，总数：".$fail_total;
			return $success_str."!==!".$fail_str;
		}else{
			return "失败，缺少参数";
		}
	}

	//添加数据库
	private function _addPoint()
	{
		$curl = new Curl();
		$data = array();
		$no = array();
		foreach ($this->_parameter as $key => $val)
		{
			$msg = iconv('utf-8', 'gbk', $val['msg']);
			$url = __CLUBAPIURL__."/add_vipPoint.php?uid=".$val['userid']."&point=".$val['point']."&type=addpoint&reason=".$msg."&sign=".md5('xywy'.$val['userid'].'addpoint');
			$result = $curl->get($url);
			if($result)
			{
				$userid = $val['userid'];
				$data[] = $val['userid'];
				$this->logs('pointpush','ing',$this->_code,implode('|@@',array($userid)).'|@@成功');
			}else{
				$userid = $val['userid'];
				$no[] = $val['userid'];
				$this->logs('pointpush','ing',$this->_code,implode('|@@',array($userid)).'|@@失败');
			}
		}

		return array('success'=>$data,'fail'=>$no);
	}
}
?>