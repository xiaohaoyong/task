<?php

/**
 * 话题关联动态数推送处理
 * User: xywy
 * Date: 2016/5/50
 * Time: 16:21
 */
class task_dynamicintegral extends task_processor
{
    private $dynamicid; //发表动态内容的id
    private $commentid; //动态内容的id
    private $userid; //用户的id
    private $type; //操作类型
    private $content; //发布的内容
    private $code; //任务序号
    private $integral1; //动态积分设置
    private $dyNum; //动态个数设置
    private $integral2; //评论分数设置
    private $comNum; //评论个数设置

    //任务处理执行方法
    public function run($parameter, $code)
    {
        if (($parameter['commentid'] || $parameter['dynamicid']) && $parameter['userid']) {
            $this->code = $code;
            $this->dynamicid = intval($parameter['dynamicid']);
            $this->commentid = intval($parameter['commentid']);
            $this->userid = intval($parameter['userid']);
            $this->type = intval($parameter['type']);
            //动态设置积分和每天所得积分动态个数
            $redis = Register::get('rdmp');
            $integrals = $redis->HGET('dc:integral:set', 'integral');
            $integral_arr = $integrals?explode('-|-', $integrals):'';
            /**
             * @type，操作状态说明1为动态添加积分，2为评论添加积分，3为动态删除积分，4为评论删除积分
             * @dynamicid，动态id
             * @commentid，评论id
             * @return; $id 创建成功返回话题id;
             */
            switch ($parameter['type']) {
                case 1:
                    $this->integral1 = $integral_arr[0];//动态积分
                    $this->dyNum = $integral_arr[1];//动态个数
                    if ($integral_arr[0] && $integral_arr[1])
                        $record = $this->dynamicAdd_regular();
                    break;
                case 2:
                    //评论设置积分和每天所得积分评论的个数
                    $this->integral2 = $integral_arr[2];//评论积分
                    $this->comNum = $integral_arr[3];//评论个数
                    if ($integral_arr[2] && $integral_arr[3])
                        $record = $this->commentAdd_regular();
                    break;
                case 3:
                    $record = $this->dynamicDel_regular();
                    break;
                default:
                    $record = $this->commentDel_regular();
                    break;
            }
            //执行日志记录
            $this->log($record);
            //敏感词保存
            $this->Sensitive();
            return $record['msg'];
        }
    }


    /**
     * 动态积分添加规则 */
    private function dynamicAdd_regular()
    {
        $dbs = Register::get('dbsdc');
        $redis = Register::get('rdmp');
        //动态获取评论字数
        $sql = 'select * from dc_dynamic where id=?';
        $dynamic = $dbs->GetRow($sql, array($this->dynamicid));
        $tm = $dynamic['id'] % 100;
        $tables = 'dc_dynamic_' . $tm;
        $sql = "select content from {$tables} where dynamicid=?";
        $content = $dbs->GetOne($sql, array($dynamic['id']));
        $this->content = $content;
        $times = date('Y-m-d', $dynamic['createtime']);
        //判断字数评论的
        $dynamicNum = mb_strlen(theme_comments($content), 'utf8');
        //当天动态获取的分数
        $mark = $redis->ZSCORE('dc:integral:dynamic:', $this->userid . '-|-' . $times);
        //判断该动态是否已经加过积分
        $isAdd = $redis->HGET('dc:integral:dynamic:gain', $this->userid . '-|-' . $dynamic['id']);
        //设置的分数
        $integral = $this->integral1;
        //设置的动态数
        $dyNum = $this->dyNum;
        //获得的总积分
        $integralAll = $integral * $dyNum;
        if ($dynamic['level'] == 0) {
            if ($mark < $integralAll && !$isAdd && $dynamicNum >= 20) {
                //评论获取的积分记录的次数
                $redis->zIncrBy('dc:integral:dynamic:', $integral, $this->userid . '-|-' . $times);
                //评论动态得分数的动态id
                $redis->HSET('dc:integral:dynamic:gain', $this->userid . '-|-' . $dynamic['id'], $integral);
                $parameter = array('msg' => '发表动态奖励', 'userid' => $this->userid, 'point' => $integral);
                $point = $this->_addPoint($parameter);
            }
            $mark < $integralAll ? ($isAdd ? $mgs = '该条动态已获取过积分' : ($dynamicNum >= 20 ? $mgs = '发表医圈动态奖励积分' : $mgs = '动态字数不符合要求')) : $mgs = '动态奖励已达到上限';
            return array('msg' => $mgs, 'code' => $point ? 1 : 0);
        } elseif ($dynamic['level'] == -4 && $dynamicNum >= 20) {
            $dcsmg = new dcmsg();
            $userlist[$this->userid] = 21;
            $dcsmg->send($userlist, '55959219', $dynamic['id'], '', $dynamic['type'], '');
            return array('msg' => '匹配敏感词不添加积分', 'code' => 1);
        }

    }

    /**
     *评论积分添加 */
    private function commentAdd_regular()
    {
        $dbs = Register::get('dbsdc');
        $redis = Register::get('rdmp');
        //动态获取评论字数
        $sql = 'select * from dc_comment where id=?';
        $comment = $dbs->GetRow($sql, array($this->commentid));
        $tm = $comment['id'] % 50;
        $tables = 'dc_comment_' . $tm;
        $sql = "select content from {$tables} where commentid=?";
        $content = $dbs->GetOne($sql, array($comment['id']));
        $this->content = $content;
        //获取评论的时间
        $times = date('Y-m-d', $comment['createtime']);
        //获取评论的字数
        $contentNum = mb_strlen($content, 'utf8');
        //当天评论获取的分数
        $mark = $redis->ZSCORE('dc:integral:comment:', $this->userid . '-|-' . $times);
        //判断该动态是否已经评论加过积分
        $isAdd = $redis->HGET('dc:integral:comment:gain', $this->userid . '-|-' . $comment['dynamicid']);
        //设置的分数
        $integral = $this->integral2;
        //设置的动态数
        $comNum = $this->comNum;
        //获得的总积分
        $integralAll = $integral * $comNum;
        if ($comment['level'] == 0) {
            if ($mark < $integralAll && !$isAdd && $contentNum >= 10) {
                //评论获取的积分的累计
                $redis->zIncrBy('dc:integral:comment:', $integral, $this->userid . '-|-' . $times);
                //评论动态得分数的动态id
                $redis->HSET('dc:integral:comment:gain', $this->userid . '-|-' . $comment['dynamicid'], $integral);
                $parameter = array('msg' => '发表评论奖励', 'userid' => $this->userid, 'point' => $integral);
                $point = $this->_addPoint($parameter);

            }
            $mark < $integralAll ? ($isAdd ? $mgs = '该条评论已获取过积分' : ($contentNum >= 10 ? $mgs = '发表医圈评论奖励积分' : $mgs = '评论字数不符合要求')) : $mgs = '评论奖励已达到上限';
            return array('msg' => $mgs, 'code' => $point ? 1 : 0, 'id' => $comment['id']);
        } elseif ($comment['level'] == 1 && $contentNum >= 10) {
            $dcsmg = new dcmsg();
            $userlist[$this->userid] = 22;
            $dcsmg->send($userlist, '55959219', '', $comment['id'], $comment['type'], '');
            return array('msg' => '匹配敏感词不添加积分', 'code' => 1);
        }
    }


    //评论积分删除
    private function commentDel_regular()
    {
        $dbs = Register::get('dbsdc');
        $redis = Register::get('rdmp');
        $sql = 'select * from dc_comment where id=? and level<0';
        $comment = $dbs->GetRow($sql, array($this->commentid));
        //获取评论的时间
        $times = date('Y-m-d', $comment['createtime']);
        //判断该评论是否存在
        $isAdd = $redis->HGET('dc:integral:comment:gain', $this->userid . '-|-' . $comment['dynamicid']);
        //当天评论获取的分数
        $mark = $redis->ZSCORE('dc:integral:comment:', $this->userid . '-|-' . $times);
        $comment['level']==-1?$info='删除评论扣除':$info='评论不符奖励规则';
        if ($isAdd && $mark >= $isAdd) {
            //扣除相应的积分
            $redis->zIncrBy('dc:integral:comment:', -$isAdd, $this->userid . '-|-' . $times);
            //评论动态得分数的动态id
            $redis->HDEL('dc:integral:comment:gain', $this->userid . '-|-' . $comment['dynamicid']);
            $parameter = array('msg' =>$info, 'userid' => $this->userid, 'point' =>-$isAdd);
            $point = $this->_addPoint($parameter);
            if ($comment['level'] == -3) {
                $dcsmg = new dcmsg();
                $userlist[$this->userid] = 21;
                $dcsmg->send($userlist, '55959219', '', $comment['id'], $comment['type'], '');
                return array('msg' => '后台屏蔽评论扣除积分', 'code' => 1);
                exit;
            }
        }
        $isAdd ? $mgs = ($mark >= $isAdd ? $info : $mgs = '未获得积分不扣除') : $mgs = '该评论未获得积分不扣除';
        return array('msg' => $mgs, 'code' => $point ? 1 : 0, 'id' => $comment['id']);
    }


    //动态积分删除
    private function dynamicDel_regular()
    {
        $dbs = Register::get('dbsdc');
        $redis = Register::get('rdmp');
        $sql = 'select * from dc_dynamic where id=? and level<0';
        $dynamic = $dbs->GetRow($sql, array($this->dynamicid));
        //获取评论的时间
        $times = date('Y-m-d', $dynamic['createtime']);
        //判断该评论是否存在
        $isAdd = $redis->HGET('dc:integral:dynamic:gain', $this->userid . '-|-' . $dynamic['id']);
        //当天评论获取的分数
        $mark = $redis->ZSCORE('dc:integral:dynamic:', $this->userid . '-|-' . $times);
        //区分后台删除和用户自己删除
        $dynamic['level']=-1?$info='删除动态扣除':$info='动态不符奖励规则';
        if ($isAdd && $mark >= $isAdd) {
            //扣除相应的积分
            $redis->zIncrBy('dc:integral:dynamic:', -$isAdd, $this->userid . '-|-' . $times);
            //评论动态得分数的动态id
            $redis->HDEL('dc:integral:dynamic:gain', $this->userid . '-|-' . $dynamic['id']);
            $parameter = array('msg' =>$info, 'userid' => $this->userid, 'point' =>-$isAdd);
            $point = $this->_addPoint($parameter);
            if ($dynamic['level'] == -3) {
                $dcsmg = new dcmsg();
                $userlist[$this->userid] = 21;
                $dcsmg->send($userlist, '55959219', $dynamic['id'], '', $dynamic['type'], '');
                return array('msg' => '后台屏蔽动态扣除积分', 'code' => 1);
                exit;
            }
        }
        $isAdd ? $mgs = ($mark >= $isAdd ? '未获得积分不扣除' : $mgs =$info) : $mgs = '该评论未获得积分不扣除';
        return array('msg' => $mgs, 'code' => $point ? 1 : 0);

    }


    /**
     * 获取积分
     */
    private function _addPoint($parameter)
    {
        $curl = new Curl();
        $msg = iconv('utf-8', 'gbk', $parameter['msg']);
        $url = __CLUBAPIURL__ . "/add_vipPoint.php?uid=" . $parameter['userid'] . "&point=" . $parameter['point'] . "&type=addpoint&reason=" . $msg . "&sign=" . md5('xywy' . $parameter['userid'] . 'addpoint');
        return $result = $curl->get($url);
    }

    /**
     * 敏感词保存列表
     */
    private function Sensitive()
    {
        $type = $this->type;
        $redis = Register::get('rdmp');
        if ($type == 1) {
            $comm = $redis->HEXISTS('mp:keywords:list', $this->dynamicid);
            if (!$comm && $keydeny = filter_deny_fir($this->content, $this->dynamicid)) {
                $redis->hset('mp:keywords:list', $this->dynamicid, $keydeny);
            }
        } elseif ($type == 2) {
            $comm = $redis->HEXISTS('mp:keywords:comment', $this->commentid);
            if (!$comm && $keydeny = filter_deny_fir($this->content, $this->commentid)) {
                $redis->hset('mp:keywords:comment', $this->commentid, $keydeny);
            }
        }
    }


    /**
     * 积分操作日志
     */
    private function log($record)
    {
        if ($record['code'] == 1) {
            $mgs = '|@@' . $this->userid . '|@@' . $record['msg'] . '|@@积分操作成功';

        } else {
            $mgs = '|@@' . $this->userid . '|@@' . $record['msg'] . '|@@积分操作失败';
        }
        $this->logs('dynamicintegral', 'ing', $this->code, $mgs);
    }


}