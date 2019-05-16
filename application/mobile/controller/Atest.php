<?php

namespace app\mobile\controller;

use think\Db;
use think\Session;
class Atest 
{

    public function index(){

        # 查找有上级关系，上级列缓存未完成的用户
        $user = Db::name('users')->field('user_id,first_leader,parents_cache')->where(['parents_cache' => ['=', 0], 'first_leader' => ['>', 0]])->order('first_leader asc')->find();

        if($user){
            if($user['first_leader'] == $user['user_id']){
                Db::name('users')->where('user_id', $user['user_id'])->update(['first_leader' => 0]);
                goto OnceMore;
            }
            # 找到用户顺位上级列
            $pcache = Db::name('parents_cache')->where(['sort' => 1, 'user_id' => $user['user_id']])->find();
            if(!$pcache){
                # 尚未开始组装上级缓存的情况....

                $first_leader = $user['first_leader'];
                $parents[] = $first_leader;

                # 查找上级的上级缓存
                $first_parents_cache = Db::name('parents_cache')->where('user_id', $first_leader)->select();
                if($first_parents_cache){
                    # 组装上级列
                    foreach($first_parents_cache as $fpc){
                        $first_parents = explode(',',$fpc['parents']);
                        // dump($first_parents);exit;
                        rsort($first_parents);
                        foreach($first_parents as $v){
                            $parents[] = (int)$v;
                        }
                    }

                    $count = count($parents) - 1;
                    if($count <= 3){
                        krsort($parents);
                        $parents_str = implode(',', $parents);
                        Db::name('parents_cache')->insert(['user_id' => $user['user_id'], 'sort' => 1, 'parents' => $parents_str, 'count' => $count]);
                        if($parents[$count] == 0){
                            Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                        }
                        goto OnceMore;
                    }else{


                        dump($count);exit;
                    }
                    
                    
                    dump($parents);exit;
                }else{
                    # 上级不存在上级缓存，设定上级的上级为0【没有上级】
                    $parents[] = 0;
                    krsort($parents);
                    $count = count($parents) - 1;
                    $parents_str = implode(',', $parents);
                    // dump($parents);exit;
                    Db::name('parents_cache')->insert(['user_id' => $user['user_id'], 'sort' => 1, 'parents' => $parents_str, 'count' => $count]);
                    if($parents[$count] == 0){
                        Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                    }
                    goto OnceMore;
                }
            }else{
                // dump($user);exit;
                $parents = explode(',',$pcache['parents']);
                if($parents[0] == 0){
                    Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                    goto OnceMore;
                }


                dump($parents);exit;
            }
        }else{
            exit('END');
        }
        OnceMore:
            echo "<h3>稍后！程序再次执行...【".$user['user_id']."】</h3>";
            echo "<script>setTimeout(function(){window.location.replace(location.href);},100);</script>";
            exit;
    }
}