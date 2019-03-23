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
        $field = "ind_per, agent_per, team_nums";
        $condition = Db::name('agent_level')
                    ->field($field)
                    ->where('level',$agent_level)->find();
        return $condition;
    }
    //升级
    public function upgrade_agent($agent_id){
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        //最大等级
        $max_level = Db::name('agent_level')->max('level');
        $field = "ind_per, agent_per";
        //用户业绩
        $agent_cond = Db::name('agent_performance')
                    ->field($field)
                    ->where('user_id',$agent_id)->find();
        $agent_cond['agent_per'] = $agent_cond['ind_per'] + $agent_cond['agent_per'];
        $team_nums = $this->get_team_num($agent_id);
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
        dump($agent_level);
        dump($team_nums);
        dump($bool);
        dump($agent_cond);
        dump($condition);
    }
    /**
     * 升级
     */
    // public function upgrade_agent($agent_id)
    // {
    //     //用户等级
    //     $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
    //     //最大等级
    //     $max_level = Db::name('agent_level')->max('level');
    //     // dump($max_level);
    //     $agent_info = $this->up_condition($agent_id);
    //     $ind_per = $agent_info['ind_per'];   //个人业绩
    //     $agent_per = $agent_info['in_per'] + $agent_info['agent_per'];  //团队业绩
    //     $agent_nums = $this->get_team_num($agent_id); //团队人数
    //     switch($agent_level)
    //     {
    //         case 0: if(($ind_per>=5) && ($agent_per>=50) ){
    //                     Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>1]);
    //                     // dump(111);
    //                  };
    //                 break;
    //         case 1: if(($ind_per>=10) && ($agent_per>=200) && ($agent_nums > 2)){
    //                     Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>2]);
    //                     // dump(222);
    //                 };break;
    //         case 2: if(($ind_per>=15) && ($agent_per>=1000) && ($agent_nums > 2)){
    //                     Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>3]);
    //                     // dump(333);
    //                 };break;
    //         case 3: if(($ind_per>=20) && ($agent_per>=5000) && ($agent_nums > 2)){
    //                     Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>4]);
    //                     // dump(444);
    //                 };break;
    //         case 4: if(($ind_per>=30) && ($agent_per>=12000) && ($agent_nums > 2)){
    //                     Db::name('users')->where('user_id',$agent_id)->update(['distribut_level'=>5]);
    //                     // dump(555);
    //                 };break;
    //         case 5: break;
            
    //     }
        
    // }

    
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
    
    /**
     * 判断团队同级人数
     */
    public function get_team_num($user_id){
        ini_set('max_execution_time', '0');

        $user_level = M('users')->where('user_id',$user_id)->value('distribut_level');
        $all = M('users')->field('user_id,first_leader,distribut_level')->where('first_leader',$user_)->select();

        $values = [];
        foreach ($all as $item) {
            $values[$item['first_leader']][] = $item;
        }

        $coumun = $this->membercount($user_id, $values,$user_level);
        
       return $coumun;
       
    }

    public function membercount($id, $data, $user_level)
    {
        $count = 0;
        // dump($data);die;
        $list = [];
        foreach($data as $key => $value){
            // if($key == 0){
            //     $list = $value;
            // }else{
                $list = array_merge($value);
                $list1 = [];
                foreach($list as $k=>$v){
                    
                    // if($k == 'distribut_level'){
                        // $team = 
                        // dump($v);//die;
                        $list1 = array_merge($value);
                        dump($list1);
                    // }
                }
                
                // $this->membercount()
            // }
        }
        dump(11);die;
        foreach($list as $k=>$v){
            if($v['distribut_level'] >= $user_level){
                $count += 1;
            }else{
                unset($list[$k]);
            }
        }

        return $count;
        
    }
    // public function membercount($id, $data)
    // {
    //     $count = 0;
    //     $num = count($data[$id]);
    //     dump($data[$id]);die;
    //     if (empty($data[$id])) {
    //         return $num;
    //     } else {
    //         $mun = 0;
    //         foreach ($data[$id] as $key => $value) {
    //             if (empty($data[$value['user_id']])) {
    //                 continue;
    //             } else {
    //                 $mun += intval($this->membercount($value['user_id'], $data));
    //             }
    //         }
    //         $num += $count;
    //     }
    //     return $num + $mun;
    // }
   


}


?>