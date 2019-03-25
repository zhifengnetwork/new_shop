<?php
/**
 * 级别升级逻辑
 * ----------------------------------------------
 * @author wu
 * @date 2019-3-25
 */
namespace app\common\logic;

use think\Model;
use think\Db;

class LevelLogic extends Model
{
    
    public function user_in($leaderId)
    {
        $frist_leader_info = $this->user_info_agent($leaderId);
        //判断是否有上级,有就升级
        if($frist_leader_info){
            $leader_list = $this->user_info_agent($leaderId);
            foreach($leader_list as $k=>$v){
                foreach($v as $ $k1 => $v1){                  
                    $this->upgrade_agent($v1);
                }
            }
        }

    }
    
    /**
     * 获取用户升级条件
     */
    private function up_condition($agent_id)
    {
        $agent_info = Db::name('agent_performance')->where(['user_id'=>$agent_id])->find();
        return $agent_info;
    }

    
    /**
     * 升级条件
     */
    public function condition($agent_level)
    {
        $field = "ind_goods_sum, agent_goods_sum, team_nums";
        $condition = Db::name('agent_level')
                    ->field($field)
                    ->where('level',$agent_level)->find();
        return $condition;
    }
    //升级
    public function upgrade_agent($agent_id){
        global $list_test;
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        //最大等级
        $max_level = Db::name('agent_level')->max('level');
        $field = "ind_goods_sum, agent_goods_sum";
        //用户业绩
        $agent_cond = Db::name('agent_performance')
                    ->field($field)
                    ->where('user_id',$agent_id)->find();
        $agent_cond['agent_goods_sum'] = $agent_cond['ind_goods_sum'] + $agent_cond['agent_goods_sum'];
        $team_nums = $this->get_down_nums($agent_id);
        $agent_cond['team_nums'] = $team_nums;
        //升级条件
        $condition = $this->condition($agent_level+1);
        $bool = true;
        foreach($agent_cond as $k=>$v){
            if($v < $condition[$k]){
                $bool = false;
                break;
            }
        }
        if($bool == true){
            if($agent_level != $max_level){
                Db::name('users')->where('user_id',$agent_id)->setInc('distribut_level');
            }
        }
    }
    
    /**
     * 获取推荐上级id
     */
    public function user_info_agent($data)
    {
        $recUser  = $this->getAllUp($data);
        $list = array_column($recUser,'user_id');
        return array('recUser'=>$list);
    }
    
    /**
     * 获取所有上级
     */
    public function getAllUp($invite_id,&$userList=array())
    {
        $field  = "user_id,first_leader,is_lock";
        $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
        if($UpInfo)  //有上级
        {
            $userList[] = $UpInfo;

            $this->getAllUp($UpInfo['first_leader'],$userList);
        }
        
        $list = array_column($userList,'user_id');
        
        return $userList;
    }
    
    // /**
    //  * 获取团队下级id
    //  */
    // public function get_team_id($user_id){
    //     ini_set('max_execution_time', '0');
    //     global $list_downid;
    //     $user_level = M('users')->where('user_id',$user_id)->value('distribut_level');
    //     $all = M('users')->field('user_id,first_leader,distribut_level')->where('first_leader',$user_id)->select();
    //     $list = array_column($all,'user_id');
    //     foreach($list as $k =>$v){
    //         $list_downid[]=$v;
    //         $this->get_team_id($v);
    //     }
       
    // }
    // /**
    //  * 递归获取团队下级同级人数
    //  */
    // public function membercount($agent_id)
    // {
    //     global $list_downid;
    //     $test = $this->get_team_id($agent_id);
    //     $down_level = [];
    //     $count = 0;
    //     $agent_level = M('users')->where('user_id',$agent_id)->value('distribut_level');
    //     //获取下级等级
    //     if($list_downid){
    //         foreach($list_downid as $k=>$v){
    //             $level = M('users')->where('user_id',$v)->value('distribut_level');
    //             array_push($down_level,$level);
    //         }
    //         //下级同等级数
    //         foreach($down_level as $k1=>$v1){
    //             if($v1 == $agent_level){
    //                 $count += 1;
    //             }
    //         }

    //     }

    //     return $count;
        
    // }
    /**
     * 获取团队下级同级人数
     */
    public function get_down_nums($agent_id)
    {
        //获取下级id列表
        $agent_level = M('users')->where('user_id',$agent_id)->value('distribut_level');
        $d_info = Db::query("select `user_id`, `first_leader`,`parents` from `tp_users` where 'first_leader' = $agent_id or parents like '%,$agent_id,%'");
        if($d_info){
            $id_array =[];
            foreach($d_info as $k=>$v){
                array_push($id_array ,$v['user_id']);
            }
        }
        //获取同级人数
        if($id_array){
            $lev_list = [];
            foreach($id_array as $k){
                if($k){
                    $l = Db::query("select `distribut_level` from `tp_users` where `user_id` = $k");
                    $lev_list[$k] = array_column($l,'distribut_level');
                }
            }
            $count = 0;
            foreach($lev_list as $k1=>$v1){
                if($v1[0] == $agent_level){
                    $count += 1;
                }
            }
        }
        return $count;
    }

}


?>