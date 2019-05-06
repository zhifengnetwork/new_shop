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
        ignore_user_abort(true);
        set_time_limit(0);
        $data = file_get_contents("php://input");

        $frist_leader_info = $this->user_info_agent($leaderId);
        //最大等级
        $max_level = Db::name('agent_level')->max('level');
        // $agent_level = M('users')->where('user_id',$agent_id)->value('distribut_level');
        // $d_info = Db::query("select `user_id` from `tp_users` where 'first_leader' = $leaderId or parents like '%,$leaderId,%'");dump($d_info);
        // get_down_nums($d_info);
        //判断是否有上级,有就升级
        if($frist_leader_info){
            foreach($frist_leader_info as $k=>$v){   
                //现在等级
                // $agent_level = M('users')->where('user_id',$v)->value('distribut_level');
                $agent_level = Db::query("select `distribut_level` from `tp_users` where `user_id` = '$v'");
                // dump($agent_level);die;
                $agent_level=$agent_level[0]['distribut_level'];
                //所有下级列表
                $d_info = Db::query("select `user_id` from `tp_users` where 'first_leader' = $v or parents like '%,$v,%'");//dump($d_info);
                //条件团队人数
                // $team_nums = Db::name('agent_level')->where('level',$agent_level+1)->value('team_nums');//dump($team_nums);
                $team_nums = Db::query("select `team_nums` from `tp_agent_level` where `level` = $agent_level+1");
                $team_nums=$team_nums[0]['team_nums'];
                $count = 0;
                // if(!isset($d_info) || empty($d_info)){
                //     continue;
                // }
                foreach($d_info as $k1=>$v1){
                    //下级等级
                    $l = Db::name('users')->where('user_id',$v1['user_id'])->value('distribut_level');//dump($l);
                    // $l = Db::query("select `distribut_level` from `tp_users` where `user_id` = ".$v1['user_id']);
                    // $l=$l[0]['distribut_level'];
                    if($l >= $agent_level){
                        $count += 1;
                        //如果同级人数达到升级条件规定,跳出到下一步
                        if($count >= $team_nums){
                            // continue;
                            break;
                        }
                    }

                }
                if($count < $team_nums){
                    continue;
                }else{
                    dump($v.':'.$count);
                    $this->upgrade_agent($v,$max_level,$count);
                    // Db::name('admin_log')->insert(['log_info'=>$v]);
                }
                // dump('111d   '.$count);
                // $num = $this->get_down_nums($v,$d_info);dump($num);            
                // $this->upgrade_agent($v,$max_level);
            }
        }
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
    public function upgrade_agent($agent_id,$max_level,$count){
        ignore_user_abort(true);
        set_time_limit(0);
        $data = file_get_contents("php://input");

        global $list_test;
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        //最大等级
        // $max_level = Db::name('agent_level')->max('level');
        $field = "ind_goods_sum, agent_goods_sum";
        //获取用户业绩
        //所有直推下级总业绩
        $down_nums = $this->get_down_all($agent_id);
        $agent_cond = Db::name('agent_performance')
                    ->field($field)
                    ->where('user_id',$agent_id)->find();
        //团队业绩 = 自己业绩 + 所有下级总业绩
        $agent_cond['agent_goods_sum'] = $agent_cond['ind_goods_sum'] + $agent_cond['agent_goods_sum'];
        //个人业绩 = 自己业绩 + 所有直推下级总业绩
        $agent_cond['ind_goods_sum'] = $agent_cond['ind_goods_sum'] + $down_nums;
        //团队标准（团队同级人数）
        // $d_info = Db::query("select `user_id`  from `tp_users` where 'first_leader' = $agent_id or parents like '%,$agent_id,%'");
        // dump($d_info);
        // $team_nums = $this->get_down_nums($agent_id,$agent_level,$d_info);
        $agent_cond['team_nums'] = $count;//$team_nums;
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
                // Db::name('admin_log')->insert(['log_info'=>$agent_id]);
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
        return $list;
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
    public function get_down_nums($agent_id,$d_info)
    {
        //获取下级id列表
        $agent_level = M('users')->where('user_id',$agent_id)->value('distribut_level');
        // $d_info = Db::query("select `user_id`, `first_leader`,`parents` from `tp_users` where 'first_leader' = $agent_id or parents like '%,$agent_id,%'");
        // $d_info = Db::query("select `user_id`  from `tp_users` where 'first_leader' = $agent_id or parents like '%,$agent_id,%'");
        if($d_info){
            // $id_array =[];
            $count = 0;
            foreach($d_info as $k=>$v){
                // dump($v);    
                $l = Db::name('users')->where('user_id',$v['user_id'])->value('distribut_level');//dump($l);
                if($l >= $agent_level){
                    $count += 1;
                }       
                // $l = Db::query("select `distribut_level` from `tp_users` where `user_id` = $v["user_id"]");
                // $lev_list[$k] = array_column($l,'distribut_level');
                // array_push($id_array ,$v['user_id']);
            }
        }
        //获取同级人数
        // if($id_array){
        //     $lev_list = [];
        //     foreach($id_array as $k){
                // if($k){
                //     $l = Db::query("select `distribut_level` from `tp_users` where `user_id` = $k");
                //     $lev_list[$k] = array_column($l,'distribut_level');
                // }
        //     }
        //     $count = 0;
        //     foreach($lev_list as $k1=>$v1){
        //         if($v1[0] == $agent_level){
        //             $count += 1;
        //         }
        //     }
        // }
        // dump($count);
        return $count;
    }

    /**
     * 获取所有直推下级业绩
     */
    public function get_down_all($agent_id)
    {
        //获取直推下级id
        $ids = Db::name('users')->where('first_leader',$agent_id)->field('user_id')->select();
        // $id_array = [];
        $down_count = 0;
        if($ids){
            foreach($ids as $k=>$v){
                // dump($v);
                $count = Db::name('agent_performance')->where('user_id',$v['user_id'])->value('ind_goods_sum');
                $down_count = $down_count + $count;
                // array_push($id_array ,$v['user_id']);
            }
        }
        // dump($id_array);
        // $down_count = 0;
        // foreach($id_array as $v){
        //     $count = Db::name('agent_performance')->where('user_id',$v)->value('ind_goods_sum');
        //     $down_count = $down_count + $count;
        // }
        return $down_count;
    }

}