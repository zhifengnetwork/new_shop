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
            //查询对应openid用户的“旧表的余额+返佣+签到邀新-提现-余额支付”是否等于“新的余额”
            $user = Db::name('users')->where('openid',$ids['openid'])->field('user_id,user_money')->find();//新表用户和余额
            $all_tixian = Db::name('withdrawals')->where("user_id",$user['user_id'])->whereIn('status',array(0,1,2))->sum('money');//该用户所有提现
            $all_yongjin = Db::name('distrbut_commission_log')->where("to_user_id",$user['user_id'])->sum('money');//该用户所有返佣
            $all_qiandao = Db::name('commission_log')->where("user_id",$user['user_id'])->sum('money');//该用户所有签到邀新
            // $all_pay = Db::name('order')->where("user_id",$user['user_id'])->sum('user_money');//该用户所有余额支付
            $all_pay = Db::name('account_log')->where("user_id",$user['user_id'])->where('order_id','>',0)->sum('user_money');//该用户所有余额支付
            // dump($user['user_id']);dump($all_pay);die;
            //“旧表的余额+返佣+签到邀新-提现-余额支付”应该总的余额
            $user_money =  $ids['old_money'] + $all_yongjin + $all_qiandao - $all_tixian - $all_pay;
            if($user_money == $user['user_money']){
                //改变检查状态
                Db::name('haiyi_user')->where('openid',$ids['openid'])->update(['check_status'=>1]);
                // Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
            }else{
                //插入bug表里
                $ins = Db::name('haiyi_bug')->insert([
                    'user_id'   =>$user['user_id'],
                    'openid'    =>$ids['openid'],
                    'old_money' =>$ids['old_money'],
                    'new_money' =>$ids['new_money'],
                    'ti_xian'   =>$all_tixian,
                    'yong_jin'  =>$all_yongjin,
                    'qian_dao'  =>$all_qiandao,
                    'pay'       =>$all_pay,
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
        // $this->to_check_test();
        // $count = Db::name('hs_sz_yi_member')->count();//dump($count);
        for($i=0;$i<500;$i++){
            $this->to_check();
        }
        // $bool = M('haiyi_user')->where('user_id',8831)->update(['new_money'=>1604.71,'yong_jin'=>1497.27]);
        // dump($bool);
    }

    //第一次筛选
    // public function first_check()
    // {
    //     $old_openids = Db::name('hs_sz_yi_member')->where('check_status',0)->find();//旧的用户数据  //->field('id,uid,openid,credit2,nickname')
    //     if($old_openids){
    //         if($old_openids['uid'] == 0){
    //             $old_money = 0;
    //         }else{
    //             $old_user = Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->find();
    //             $old_money = $old_user['credit2'];
    //         }

    //         $user = Db::name('users')->where('openid',$old_openids['openid'])->field('user_id,user_money')->find();//新表用户和余额
    //         if($user['user_money'] != $old_money){
    //            //插入怀疑表里
    //            $ins = Db::name('haiyi_user')->insert([
    //             'user_id'   =>$user['user_id'],
    //             'openid'    =>$old_openids['openid'],
    //             'old_user_id' =>$old_openids['id'],
    //             'old_money' =>$old_money,
    //             'new_money' =>$user['user_money'],
    //             // 'ti_xian'   =>$all_tixian,
    //             // 'yong_jin'  =>$all_yongjin,
    //             // 'qian_dao'  =>$all_qiandao,
    //             'create_time' =>time()
    //             ]);
    //             if($ins){
    //                 //改变为怀疑状态
    //                 Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>2]);
    //                 Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>2]);
    //                 Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>2]); 
    //                 echo $user['user_id'].'<br />';
    //             } 
    //         }else{
    //             //改变为已检查状态
    //             Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>1]);
    //             Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>1]);
    //             Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
                
    //         }
    //     }else{
    //         echo "第一次搞完了'<br />'";
    //     }
    // }

    // //判断新的余额是否等于佣金+签到-提现-支付
    // public function three_check()
    // {
    //     $res = Db::name('haiyi_bug')->where('new_status',0)->find();
    //     dump($res);//die;
    //     if($res){
    //         $all = $res['yong_jin'] + $res['qian_dao'] - $res['ti_xian'] - $res['pay'];
    //         if($all == $res['new_money']){
    //             $res = Db::name('haiyi_bug')->where('openid',$res['openid'])->update(['new_status'=>1]);
    //         }else{
    //             $res = Db::name('haiyi_bug')->where('openid',$res['openid'])->update(['new_status'=>2]);
    //         }
    //     }else{
    //         echo 'ok<br />';
    //     }
    // }


    public function to_check()
    {
        $old_openids = Db::name('hs_sz_yi_member')->where('check_status',0)->find();//旧的用户数据  //->field('id,uid,openid,credit2,nickname')
        if($old_openids){
            if($old_openids['uid'] == 0){
                $old_money = 0;
            }else{
                $old_user = Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->find();
                $old_money = $old_user['credit2'];
            }

            //查询对应openid用户的“旧表的余额+返佣+签到邀新-提现-余额支付”是否等于“新的余额”
            $user = Db::name('users')->where('openid',$old_openids['openid'])->field('user_id,user_money')->find();//新表用户和余额
            $all_tixian = Db::name('withdrawals')->where("user_id",$user['user_id'])->whereIn('status',array(0,1,2))->sum('money');//该用户所有提现
            $all_yongjin = Db::name('distrbut_commission_log')->where("to_user_id",$user['user_id'])->sum('money');//该用户所有返佣
            $all_qiandao = Db::name('commission_log')->where("user_id",$user['user_id'])->sum('money');//该用户所有签到邀新
            // $all_pay = Db::name('order')->where("user_id",$user['user_id'])->sum('user_money');//该用户所有余额支付
            $all_pay = Db::name('account_log')->where("user_id",$user['user_id'])->where('order_id','>',0)->sum('user_money');//该用户所有余额支付
            // dump($user['user_id']);dump($all_pay);die;
            //“旧表的余额+返佣+签到邀新-提现-余额支付”应该总的余额
            $user_money =  $old_money + $all_yongjin + $all_qiandao - $all_tixian - $all_pay;

            $a=floor($user['user_money']*100);
            $b=floor($user_money*100);
            //$user['user_money'] != $user_money
            if($a != $b){
               //插入怀疑表里
               $ins = Db::name('haiyi_user')->insert([
                'user_id'   =>$user['user_id'],
                'openid'    =>$old_openids['openid'],
                'old_user_id' =>$old_openids['id'],
                'old_money' =>$old_money,
                'new_money' =>$user['user_money'],
                'ti_xian'   =>$all_tixian,
                'yong_jin'  =>$all_yongjin,
                'qian_dao'  =>$all_qiandao,
                'pay'       =>$all_pay,
                'create_time' =>time()
                ]);
                if($ins){
                    //改变为怀疑状态
                    Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>2]);
                    Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>2]);
                    Db::name('users')->where('openid',$old_openids['openid'])->update(['check_status'=>2]); 
                    echo $user['user_id'].'<br />';
                } 
            }else{
                //改变为已检查状态
                Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>1]);
                Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>1]);
                Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
                
            }
        }else{
            echo "搞完了'<br />'";
        }
    }

    public function to_check_test()
    {
        // $old_openids = Db::name('hs_sz_yi_member')->where('check_status',0)->find();//旧的用户数据  //->field('id,uid,openid,credit2,nickname')
        // if($old_openids){
        //     if($old_openids['uid'] == 0){
        //         $old_money = 0;
        //     }else{
        //         $old_user = Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->find();
        //         $old_money = $old_user['credit2'];
        //     }
        $user['user_id']=9532;
            //查询对应openid用户的“旧表的余额+返佣+签到邀新-提现-余额支付”是否等于“新的余额”
            $user = Db::name('users')->where('user_id',$user['user_id'])->field('user_id,user_money')->find();//新表用户和余额
            $all_tixian = Db::name('withdrawals')->where("user_id",$user['user_id'])->whereIn('status',array(0,1,2))->sum('money');//该用户所有提现
            $all_yongjin = Db::name('distrbut_commission_log')->where("to_user_id",$user['user_id'])->sum('money');//该用户所有返佣
            $all_qiandao = Db::name('commission_log')->where("user_id",$user['user_id'])->sum('money');//该用户所有签到邀新
            // $all_pay = Db::name('order')->where("user_id",$user['user_id'])->sum('user_money');//该用户所有余额支付
            $all_pay = Db::name('account_log')->where("user_id",$user['user_id'])->where('order_id','>',0)->sum('user_money');//该用户所有余额支付
            // dump($user['user_id']);dump($all_pay);die;
            //“旧表的余额+返佣+签到邀新-提现-余额支付”应该总的余额
            $user_money =  $old_money + $all_yongjin + $all_qiandao - $all_tixian - $all_pay;
            // var_dump($user_money);echo "```";var_dump($user['user_money']);die;
            // if(0.6 != 0.60){
            //     echo 666;
            // }else{
            //     echo 777;
            // }
            $a=floor($user['user_money']*100);
            $b=floor($user_money*100);
            var_dump($a);
            var_dump($b);
            if($a != $b){
                var_dump($user_money);echo "```";var_dump($user['user_money']);//die;
                echo 123;die;
               //插入怀疑表里
               $ins = Db::name('haiyi_user')->insert([
                'user_id'   =>$user['user_id'],
                'openid'    =>$old_openids['openid'],
                'old_user_id' =>$old_openids['id'],
                'old_money' =>$old_money,
                'new_money' =>$user['user_money'],
                'ti_xian'   =>$all_tixian,
                'yong_jin'  =>$all_yongjin,
                'qian_dao'  =>$all_qiandao,
                'pay'       =>$all_pay,
                'create_time' =>time()
                ]);
                if($ins){
                    //改变为怀疑状态
                    Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>2]);
                    Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>2]);
                    Db::name('users')->where('openid',$old_openids['openid'])->update(['check_status'=>2]); 
                    echo $user['user_id'].'<br />';
                } 
            }else{
                echo 456;die;
                //改变为已检查状态
                Db::name('hs_sz_yi_member')->where('openid',$old_openids['openid'])->update(['check_status'=>1]);
                Db::name('hs_mc_members')->where('uid',$old_openids['uid'])->update(['check_status'=>1]);
                Db::name('users')->where('user_id',$user['user_id'])->update(['check_status'=>1]);
                
            }
        // }else{
        //     echo "搞完了'<br />'";
        // }
    }



}