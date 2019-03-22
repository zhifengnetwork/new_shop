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
        //判断是否有上级,有就升级
        if($frist_leader_info){
            $leader_list = $this->user_info_agent($leaderId);
            foreach($leader_list as $k=>$v){
                foreach($v as $ $k1 => $v1){
                    // dump($v1);
                    $this->upgrade_agent($v1);

                }
            }

        }else{
            return false;
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
     * 升级
     */
    public function upgrade_agent($agent_id)
    {
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        
        $agent_info = $this->up_condition($agent_id);
        $ind_per = $agent_info['ind_per'];   //个人业绩
        $agent_per = $agent_info['in_per'] + $agent_info['agent_per'];  //团队业绩
        $agent_nums = $this->get_team_num($agent_id); //团队人数
        // dump($ind_per);
        // dump($agent_per);
        // dump($agent_nums);
        switch($agent_level)
        {
            case 0: if(($ind_per>=5) && ($agent_per>=50) ){
                        Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>1]);
                    }else{
                        dump(000); 
                        return false;
                    };break;
            case 1: if(($ind_per>=10) && ($agent_per>=200) && ($agent_nums > 2)){
                        Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>2]);
                    }else{
                        dump(111);
                        return false;
                    };break;
            case 2: if(($ind_per>=15) && ($agent_per>=1000) && ($agent_nums > 2)){
                        Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>3]);
                    }else{
                        dump(222);
                        return false;
                    };break;
            case 3: if(($ind_per>=20) && ($agent_per>=5000) && ($agent_nums > 2)){
                        Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>4]);
                    }else{
                        dump(333);
                        return false;
                    };break;
            case 4: if(($ind_per>=30) && ($agent_per>=12000) && ($agent_nums > 2)){
                        Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>5]);
                    }else{
                        dump(444);
                        return false;
                    };break;
            case 5: return false;
            
        }
        
    }
    
    /**
     * 获取推荐上级id
     */
    public function user_info_agent($data)
    {
        // $frist_leader_id = Db::name('users')->where('user_id',$agent_id)->value('first_leader');
        // $frist_leader_info = DB::name('users')->where('user_id',$frist_leader_id)->find();
        // return $frist_leader_info?$frist_leader_info:false;
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
            // dump($us);die;
            $this->getAllUp($UpInfo['first_leader'],$userList);
        }
        // dump($userList);
        $list = array_column($userList,'user_id');
        // dump($list);
        
        return $userList;
    }
    
    /**
     * 判断团队同级人数
     */
    public function get_team_num($user_id){
        ini_set('max_execution_time', '0');

        // $user_id = I('user_id');
        $user_level = M('users')->where('user_id',$user_id)->value('distribut_level');
        // dump($user_id);die;
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
        
    //    M('users')->where(['user_id'=>$user_id])->update(['underling_number'=>$coumun]);
        
       return $coumun;
       
    }

    public function membercount($id, $data, $user_level)
    {
        $count = 0;
        // $num = count($data[$id]);
        foreach($data as $key => $value){
            if($key == 0){
                $list = $value;
            }else{
                $list = array_merge($list, $value);
            }
        }
        foreach($list as $k=>$v){
            if($v['distribut_level'] == $user_level){
                $count += 1;
            }else{
                unset($list[$k]);
            }
        }
        // dump($list);
        // dump($count);
        return $count;
        
    }

   


}


?>