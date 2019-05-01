<?php

/**
 * tpshop
 * 销售设置控制器
 * ----------------------------------------------
 * @author wu
 * Date 2019-3-25
 */
namespace app\admin\controller;

use think\Db;
use think\Page;
use think\Loader;
use app\common\logic\PerformanceLogic;
use app\admin\logic\UsersLogic;
use think\Cache;

/**
*  分销
**/
class Distribution extends Base
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 销售等级列表
     */
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

    /**
     * 分销商等级
     */
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
			$level = M('agent_level')->where('level_id',$data['level_id'])->value('level');
            $r = D('agent_level')->where('level_id=' . $data['level_id'])->delete();
			M('goods_commission')->where('level',$level)->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    //业绩列表
    public function per_list()
    {
        $Ad = M('agent_performance');
        $p = input('p/d');
        $ctime = urldecode(I('ctime'));
        $ttype = I('ttype');
        $user_name = I('user_name');
        $min = I('min');
        $max = I('max');
        $per_type = I('per_type');
        $where = [];

        if($user_name){
            $user['nickname'] = ['like', "%$user_name%"];
            
            $id_list = M('users')->where($user)->column('user_id');
            
            $where['user_id'] = $id_list ? ['in',$id_list] : '';
        }
        
        if($ctime){
            $gap = explode(' - ', $ctime);
            $time_val = array('1'=>"create_time",'2'=>"update_time");
            $where[$time_val[$ttype]] = [['>= time',strtotime($gap[0])],['< time',strtotime($gap[1]." 23:59:59")],'and'];
        }
        
        if ($min || $max) {
            $val = array('1'=>"ind_per",'2'=>"agent_per",'3'=>"ind_goods_sum",'4'=>"agent_goods_sum");
            $where[$val[$per_type]] = ['between',[floatval($min),floatval($max)]];
        }
        
        $res = $Ad->where($where)->order('performance_id','desc')->page($p . ',20')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $user_ids = array_column($list, 'user_id');
        $avatar = get_avatar($user_ids);

        foreach ($list as $key => $value) {
            $list[$key]['head_pic'] = $avatar[$value['user_id']];
        }
        $this->assign('user_name',$user_name);
        $this->assign('start_time',$gap[0]);
        $this->assign('end_time',$gap[1]);
        $this->assign('ctime',$gap[0].' - '.$gap[1]);
        $this->assign('ttype',$ttype);
        $this->assign('min',$min);
        $this->assign('max',$max);
        $this->assign('per_type',$per_type);
        $this->assign('list', $list);
        $count = $Ad->where($where)->count();
        // $count = $Ad->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    //业绩日志
    public function per_log()
    {
        $Ad = M('agent_performance_log');
        $p = input('p/d');
        $ctime = urldecode(I('ctime'));
        $user_name = I('user_name');
        $order_sn = I('order_sn');
        $permin = I('permin');
        $permax = I('permax');
        
        $where = [];

        if($user_name){
            $user['nickname'] = ['like', "%$user_name%"];
            $id_list = M('users')->where($user)->column('user_id');
            $where['user_id'] = $id_list ? ['in',$id_list] : '';
        }
        if($ctime){
            $gap = explode(' - ', $ctime);
            $where['create_time'] = [['>= time',strtotime($gap[0])],['< time',strtotime($gap[1]." 23:59:59")],'and'];
        }
        if ($order_sn) {
            $where['order_sn'] = ['like',"%$order_sn%"];
        }
        if ($permin || $permax) {
            $where['money'] = ['between',[floatval($permin),floatval($permax)]];
        }

        $res = $Ad->where($where)->order('performance_id','desc')->page($p . ',20')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $user_ids = array_column($list, 'user_id');
        $avatar = get_avatar($user_ids);
        foreach ($list as $key => $value) {
            $list[$key]['head_pic'] = $avatar[$value['user_id']];
        }

        $this->assign('user_name',$user_name);
        $this->assign('start_time',$gap[0]);
        $this->assign('end_time',$gap[1]);
        $this->assign('ctime',$gap[0].' - '.$gap[1]);
        $this->assign('order_sn',$order_sn);
        $this->assign('permin',$permin);
        $this->assign('permax',$permax);
        $this->assign('list', $list);
        $count = $Ad->where($where)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('count',$count);
        $this->assign('page', $show);
        return $this->fetch();
    }

    //业绩日志详情
    public function log_detail()
    {
        $id = input('id/d');
        $detail = M('agent_performance_log')->where('performance_id',$id)->find();
        
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    //返佣日志
    public function commission_log()
    {
        $Ad = M('distrbut_commission_log');
        $p = input('p/d');

        $ctime = urldecode(I('ctime'));
        $user_name = I('user_name');
        $order_sn = I('order_sn');
        
        $where = [];
        $log_ids = '';

        if($user_name){
            $user['nickname'] = ['like', "%$user_name%"];
            $id_list = M('users')->where($user)->column('user_id');
            $where['user_id|to_user_id'] = $id_list ? ['in',$id_list] : '';
        }
        if($ctime){
            $gap = explode(' - ', $ctime);
            $where['create_time'] = [['>= time',strtotime($gap[0])],['< time',strtotime($gap[1]." 23:59:59")],'and'];;
        }
        if ($order_sn) {
            $where['order_sn'] = ['like',"%$order_sn%"];
        }
        
        $res = $Ad->where($where)->order('log_id','desc')->page($p . ',20')->select();
        if ($res) {
            foreach ($res as $val) {
                $id_lists[] = $val['log_id'];
                $list[] = $val;
            }
            $log_ids = implode(',',$id_lists);
        }

        $user_ids = array_column($list, 'user_id');
        $to_user_ids = array_column($list, 'to_user_id');
        $all_user_ids = array_merge($user_ids,$to_user_ids);
        $user_name = M('users')->whereIn('user_id',$all_user_ids)->column('user_id,nickname,mobile');
        $avatar = get_avatar($all_user_ids);

        foreach ($list as $key => $value) {
            $list[$key]['user_name'] = $user_name[$value['user_id']]['nickname'] ?: $user_name[$value['user_id']]['mobile'];
            $list[$key]['to_user_name'] = $user_name[$value['to_user_id']]['nickname'] ?: $user_name[$value['to_user_id']]['mobile'];
            $list[$key]['user_head_pic'] = $avatar[$value['user_id']];
            $list[$key]['to_user_head_pic'] = $avatar[$value['to_user_id']];
        }
       
        $this->assign('log_ids',$log_ids);
        $this->assign('user_name',$user_name);
        $this->assign('start_time',$gap[0]);
        $this->assign('end_time',$gap[1]);
        $this->assign('ctime',$gap[0].' - '.$gap[1]);
        $this->assign('order_sn',$order_sn);
        $this->assign('list', $list);
        $count = $Ad->where($where)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('count',$count);
        $this->assign('page', $show);
        return $this->fetch();
    }
    
    //返佣日志详情
    public function commission_detail()
    {
        $id = input('id/d');
        $detail = M('distrbut_commission_log')->where('log_id',$id)->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    //购买返佣
    public function export_commission_log()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr >';
        $strTable .= '<td style="text-align:center;font-size:14px;width:120px;">ID</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">获得返利用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">所得金额</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">时间</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">描述</td>';
        $strTable .= '</tr>';
        $log_ids = I('log_ids');
        
        $condition = array();
        if ($log_ids) {
            $condition['log_id'] = ['in', explode(',',$log_ids)];
        }
        $count = DB::name('distrbut_commission_log')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $log_list = M('distrbut_commission_log')->where($condition)->order('log_id')->limit($start, 5000)->select();
            if (is_array($log_list)) {
                $user_ids = array_column($log_list,'user_id');
                $to_user_ids = array_column($log_list,'to_user_id');
                $n_user_ids = array_merge($user_ids,$to_user_ids);
                $n_user_ids = array_unique($n_user_ids);
                $user_names = Db::name('users')->where('user_id','in',$n_user_ids)->column('user_id,nickname,mobile');
                foreach ($log_list as $k => $val) {
                    $username = $user_names[$val['user_id']]['nickname'] ? $user_names[$val['user_id']]['nickname'] : $user_names[$val['user_id']]['mobile'];
                    $to_username = $user_names[$val['to_user_id']]['nickname'] ? $user_names[$val['to_user_id']]['nickname'] : $user_names[$val['to_user_id']]['mobile'];
                    $order_sn = $val['order_sn'];

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['log_id'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $username . ' </td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $to_username . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $order_sn . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['num'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . date('Y-m-d H:i', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['desc'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($log_list);
            }
        }
        $strTable .= '</table>';
        $i = ($i == 1) ? '' : '_'.$i;
        downloadExcel($strTable, '购买商品返佣明细表' . $i);
        exit();
    }

    //分销关系
    public function tree()
    {
        $cat_list = tpCache('team_tree');

        if (!$cat_list) {
            $UsersLogic = UsersLogic::relation();
            $cat_list = tpCache('team_tree');
        }
        
        if($cat_list){
            $level = array_column($cat_list, 'level');
            $heightLevel = max($level);
            $level_name = Db::name('agent_level')->column('level,level_name');

            if ($level_name) {
                foreach($cat_list as $key => $value){
                    $cat_list[$key]['level_name'] =  $level_name[$value['distribut_level']];
                }
            }
        }

        $count = count($cat_list);

        if ($count == count($cat_list,1)) {
            $count = $count ? 1 : 0;
        }
        
        $this->assign('count',$count);
        $this->assign('heightLevel',$heightLevel);
        $this->assign('cat_list',$cat_list);    
        
        return $this->fetch();
    }
}