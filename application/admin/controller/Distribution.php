<?php
namespace app\admin\controller;

use app\common\logic\LevelLogic;
use think\Db;
use think\Page;
use think\Loader;
use app\common\logic\PerformanceLogic;
/**
*  销售设置
**/
class Distribution extends Base
{
    public function _initialize()
    {
        parent::_initialize();
    }

    public function agent_level()
    {
        $Ad = M('agent_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('agent_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }
        return $this->fetch();
    }

    /**
     * 分销商等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $agentLevelValidate = Loader::validate('AgentLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$agentLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $agentLevelValidate->getError()];
            } else {
                $r = D('agent_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $agentLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$agentLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $agentLevelValidate->getError()];
            } else {
                $r = D('agent_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level_id']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $agentLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('agent_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }
}