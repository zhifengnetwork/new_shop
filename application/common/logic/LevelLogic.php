<?php

namespace app\common\logic;

use think\Model;
use think\Db;

/**
 * 级别逻辑类
 */
class LevelLogic extends Model
{
    
    public function user_in($leaderId)
    {
        $frist_leader_info = $this->user_info_agent($leaderId);
        //判断是否有上级
        // if($frist_leader_info  == false){
        //     return false;
        // }
        //判断上级是否为分销商
        // if($frist_leader_info['is_distribut'] != 1){
		// 	return false;
        // }

        //判断是否为分销商
        $agentGrade = $this->is_agent_user($user_id);
        if($agentGrade){

        }

    }

    /**
	 * 判断用户是否为代理
	 */
    private function is_agent_user($user_id)
    {
        $agent = Db::name('users')->where('user_id',$user_id)->value('is_distribut');
        return $agent;
    }
    
    /**
     * 判断团队同级人数
     */
    public function level_nums($agent_id)
    {
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        $num = Db::name('users')->where('frist_leader',$agent)
                        ->where('distribut_level',$agent_level)->count();
    }
    
    /**
     * 获取用户升级条件
     */
    public function up_condition($agent_id)
    {
        $agent_info = Db::name('agent_performance')->where(['user_id'=>$agent_id])->find();
        return $agent_info;
    }

    /**
     * 升级
     */
    private function upgrade_agent($agent_id)
    {
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        //用户业绩
        $agent_info = Db::name('agent_performance')->where(['user_id'=>$agent_id])->select();
        switch($agent_level)
        {
        case 0: if($agent_info['ind_per']>=5 && $agent_info['agent_per']>=50){
                    Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
                }else{
                    return false;
                };break;
        case 1: if($agent_info['ind_per']>=10 && $agent_info['agent_per']>=200){
                    Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
                }else{
                    return false;
                };break;
        case 2: if($agent_info['ind_per']>=15 && $agent_info['agent_per']>=1000){
                    Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
                }else{
                    return false;
                };break;
        case 3: if($agent_info['ind_per']>=20 && $agent_info['agent_per']>=5000){
                    Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
                }else{
                    return false;
                };break;
        case 4: if($agent_info['ind_per']>=30 && $agent_info['agent_per']>=12000){
            Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
        }else{
            return false;
        };break;
        
        }

    }
    
    /**
     * 获取上级信息
     */
    public function user_info_agent($agent_id)
    {
        $frist_leader_id = Db::name('users')->where('user_id',$agent_id)->value('first_leader');
        $frist_leader_info = DB::name('users')->where('user_id',$frist_leader_id)->find();

        return $frist_leader_info?$frist_leader_info:false;
    }

   


}


?>