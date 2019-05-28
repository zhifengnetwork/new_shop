<?php
namespace app\mobile\controller;

use think\Db;

class CheckUser
{


    //第二次筛选
    public function haiyi_bug()
    {
        $ids = Db::name('haiyi_user')->where('check_status',0)->find();//用户数据
        // dump($ids);die;
        if($ids){
            //查询对应openid用户的“旧表的余额+返佣+签到邀新-提现”是否等于“新的余额”
            $user = Db::name('users')->where('openid',$ids['openid'])->field('user_id,user_money')->find();//新表用户和余额
            $all_tixian = Db::name('withdrawals')->where("user_id",$user['user_id'])->whereIn('status',array(1,2))->sum('money');//该用户所有提现
            $all_yongjin = Db::name('distrbut_commission_log')->where("to_user_id",$user['user_id'])->sum('money');//该用户所有返佣
            $all_qiandao = Db::name('commission_log')->where("user_id",$user['user_id'])->sum('money');//该用户所有签到邀新
            //“旧表的余额+返佣+签到邀新-提现”应该总的余额
            $user_money =  $ids['old_money'] + $all_yongjin + $all_qiandao - $all_tixian;
            if($user_money == $user['user_money']){
                //改变检查状态
                Db::name('haiyi_user')->where('openid',$ids['openid'])->update(['check_status'=>1]);
                // Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
            }else{
                //插入bug表里
                $ins = Db::name('haiyi_bug')->insert([
                    'user_id'   =>$user['user_id'],
                    'openid'    =>$ids['openid'],
                    'old_money' =>$ids['credit2'],
                    'new_money' =>$user['user_money'],
                    'ti_xian'   =>$all_tixian,
                    'yong_jin'  =>$all_yongjin,
                    'qian_dao'  =>$all_qiandao,
                    'create_time' =>time()
                    ]);
                if($ins){
                   //改变检查状态
                    Db::name('haiyi_user')->where('openid',$ids['openid'])->update(['check_status'=>2]);
                    // Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>2]); 
                    echo $user['user_id'].'<br />';
                }
                
            }
        }else{
            echo "第二次搞完了'<br />'";
        }
        

    }

    public function each_check()
    {
        // $this->haiyi_bug();
        // $count = Db::name('hs_sz_yi_member')->count();//dump($count);
        // for($i=0;$i<500;$i++){
        //     $this->haiyi_bug();
        // }
        // $bool = M('haiyi_user')->where('user_id',8831)->update(['new_money'=>1604.71,'yong_jin'=>1497.27]);
        // dump($bool);
    }

    //第一次筛选
    public function first_check()
    {
        $old_openids = Db::name('hs_sz_yi_member')->field('id,openid,credit2,nickname')->where('check_status',0)->find();//旧的用户数据
        if($old_openids){
            $user = Db::name('users')->where('openid',$old_openids['openid'])->field('user_id,user_money')->find();//新表用户和余额
            if($user['user_money'] != $old_openids['credit2']){
               //插入怀疑表里
               $ins = Db::name('haiyi_user')->insert([
                'user_id'   =>$user['user_id'],
                'openid'    =>$old_openids['openid'],
                'old_money' =>$old_openids['credit2'],
                'new_money' =>$user['user_money'],
                // 'ti_xian'   =>$all_tixian,
                // 'yong_jin'  =>$all_yongjin,
                // 'qian_dao'  =>$all_qiandao,
                'create_time' =>time()
                ]);
                if($ins){
                    //改变为怀疑状态
                    Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>2]);
                    Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>2]); 
                    echo $user['user_id'].'<br />';
                } 
            }else{
                //改变为已检查状态
                Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>1]);
                Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
                
            }
        }else{
            echo "第一次搞完了'<br />'";
        }
    }





}