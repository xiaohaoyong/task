<?php
/**
 * 媒体号PUSH处理
 * 处理流程：获取当前媒体号全部粉丝，获取需要发送的文章，组合需要发送的内容，循环向用户发送push消息
 *
 * 汪振2016-03-31
 */
class task_mediapush extends task_processor
{
    private $actValue; //文章详情
    private $mediaid; //媒体号ID
    private $actid; //文章ID
    private $sendVal; //消息发送格式
    private $code; //任务序号
    private $users=array();
    //任务处理执行方法
    public function run($parameter,$code)
    {
        if($parameter['mediaid'] && $parameter['actid'])
        {
            $this->code=$code;
            $this->mediaid  =intval($parameter['mediaid']);
            $this->actid    =intval($parameter['actid']);

            $this->actInfo()->sendValue();

            $funs=$this->funs();
            foreach($funs as $k=>$v)
            {
                echo $v."\n";
                if(!in_array($v,$this->users)) {
                    $this->send($v);
                    $this->users[]=$v;
                }
            }
            //修改文章状态为已发表
            $data['id']=$this->actid;
            $data['level'] = 3;
            $model=new model(Register::get('dbizx'));
            $model->tablename = 'info_article';
            $list = $model->AutoExecute($data);
            return "成功，发送完成总数：".count($funs);
        }else{
            return "失败，缺少参数";
        }
    }
    //获取媒体号粉丝
    private function funs()
    {
        $dbs=Register::get('dbsclub');
        $sql="select uid from share_focus where gz_uid=?";
        $funs=$dbs->GetAll($sql,array($this->mediaid));
        $list = array_map('array_pop',$funs);
        return $list;
    }
    //获取需要发送的文章内容
    private function actInfo()
    {
        $dbs=Register::get('dbszx');
        $sql="select title,image,id,content,model from info_article where id=?";
        $actRow=$dbs->GetRow($sql,array($this->actid));

        $this->actValue=$actRow;
        return $this;
    }
    //组合需要发送的push参数
    private function sendValue()
    {
        $data['id']=$this->actValue['id'];
        $data['url']=__ZIXUNURL__."/d{$this->actValue['id']}.html?xywyfrom=h5";
        $data['title']=$this->actValue['title'];
        $data['image']=$this->actValue['image'];
        //t参数5为媒体号文章推送，6为话题推送，7为原生视频推送
        $model=$this->actValue['model'];
        if($model==4){
            $videoNum = explode('|', $this->actValue['content']);
            $data['uu'] = $videoNum[0];
            $data['vu'] = $videoNum[1];
        }
        $json['t']=$model==4?7:5;
        $json['c']=implode('|',$data);
        $post_fields = urlencode(serialize($json));

        $value['message']=$post_fields;
        $value['content']= $data['title'];
        $this->sendVal=$value;
    }
    //执行推送
    private function send($userid)
    {
        $sign=md5("d4739c4203d4aea80e95$userid");
        $this->sendVal['uid']=$userid;
        $url=__CLUBAPIURL__."/push/push.interface.php?sign=".$sign;
        $curl=new Curl();
        $return=$curl->post($url,$this->sendVal);
        $r=strpos($return,'success')!==false?"success":"fail";
        $this->logs('mediapush','ing',$this->code,implode('|@@',$this->sendVal)."|@@".$r);

        return $return;
    }

}


