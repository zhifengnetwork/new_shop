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
            $this->upgrade_agent($agent_id);
        }

    }

    /**
	 * 判断用户是否为分销商
	 */
    private function is_agent_user($user_id)
    {
        $agent = Db::name('users')->where('user_id',$user_id)->value('is_distribut');
        return $agent;
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
     * 升级
     */
    private function upgrade_agent($agent_id)
    {
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        //用户业绩
        $agent_info = $this->up_condition($agent_id);
        switch($agent_level)
        {
            case 0: if($agent_info['ind_per']>=5 && $agent_info['agent_per']>=50){
                Db::name('users')->update(['distribut_level'=>1, '$user_id'=>$agent_id]);
            }else{
                return false;
            };break;
            case 1: if($agent_info['ind_per']>=10 && $agent_info['agent_per']>=200){
                Db::name('users')->update(['distribut_level'=>2, '$user_id'=>$agent_id]);
            }else{
                return false;
            };break;
            case 2: if($agent_info['ind_per']>=15 && $agent_info['agent_per']>=1000){
                Db::name('users')->update(['distribut_level'=>3, '$user_id'=>$agent_id]);
            }else{
                return false;
            };break;
            case 3: if($agent_info['ind_per']>=20 && $agent_info['agent_per']>=5000){
                Db::name('users')->update(['distribut_level'=>4, '$user_id'=>$agent_id]);
            }else{
                return false;
            };break;
            case 4: if($agent_info['ind_per']>=30 && $agent_info['agent_per']>=12000){
                Db::name('users')->update(['distribut_level'=>5, '$user_id'=>$agent_id]);
            }else{
                return false;
            };break;
            case 5: return false;
            
        }
        
    }
    
    /**
     * 获取推荐上级
     */
    public function user_info_agent($data)
    {
        // $frist_leader_id = Db::name('users')->where('user_id',$agent_id)->value('first_leader');
        // $frist_leader_info = DB::name('users')->where('user_id',$frist_leader_id)->find();
        
        // return $frist_leader_info?$frist_leader_info:false;
        $recUser  = $this->getAllUp($data);
        return array('recUser'=>$recUser);
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
            // dump($us);die;
            $this->getAllUp($UpInfo['first_leader'],$userList);
        }
        
        return $userList;
    }
    
    /**
     * 判断团队同级人数
     */
    public function level_nums($agent_id)
    {
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        // dump($agent_level);
        $num = Db::name('users')->where('first_leader',$agent_id)
                        ->where('distribut_level',$agent_level)->count();
        if($num >= 2){
            return $num;
        }else{
            $agent_id = Db::name('users')->where('first_leader',$agent_id)
                        ->field('user_id')->select();
            // dump(111);
            dump($agent_id);
            // dump(222);die;
            foreach($agent_id as $k=>$v){
                foreach($v as $k1=>$v1){
                    $this->level_nums($v1);
                }
            }
        }
    }

    /**
     * 获取所有下级
     */
    // public function getAllDown($invite_id,&$userList=array())
    // {
    //     $field  = "user_id,first_leader,is_lock";
    //     $UpInfo = M('users')->field($field)->where(['first_leader'=>$invite_id])->select();

    //     // dump($UpInfo);die;
    //     if($UpInfo)  //有下级
    //     {
    //         $userList[] = $UpInfo;
    //         // dump($userList);die;
    //         foreach($userList as $k=>$v){

    //             $this->getAllDown($UpInfo['user_id'],$userList);
    //         }
    //     }

    //     return $userList;
    // }
    public function get_team_num($user_id){
        ini_set('max_execution_time', '0');

        // $user_id = I('user_id');
        $user_level = M('users')->where('user_id',$user_id)->value('distribut_level');
        $all = M('users')->field('user_id,first_leader,distribut_level')->select();

        $values = [];
        foreach ($all as $item) {
            $values[$item['first_leader']][] = $item;
        }
        // dump($values);die;
        //foreach ($all as $k => $v) {
            $coumun = $this->membercount($user_id, $values,$user_level);
            // dump($values);die;
            //M('users')->where(['user_id'=>$v['user_id']])->update(['underling_number'=>$coumun]);
            //$coumun += $coumun;
       // }
        
       M('users')->where(['user_id'=>$user_id])->update(['underling_number'=>$coumun]);
        
       echo $coumun;
       
    }


    public function membercount($id, $data, $user_level)
    {
        $count = 0;
        $num = count($data[$id]);
        // dump($data[$id]);
        if (empty($data[$id])) {
            return $num;
        } else {
            $mun = 0;
            foreach ($data[$id] as $key => $value) {
                // dump($user_level);die;
                if (empty($data[$value['user_id']])) {
                    continue;
                } else {
                    if($value['distribut_level'] == $user_level){

                        $mun += intval($this->membercount($value['user_id'], $data));
                    }
                }
            }
            $num += $count;
        }
        return $num + $mun;
    }

   


}


?>