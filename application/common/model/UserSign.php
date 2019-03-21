<?php
/**
 * 用户签到送佣金
 * @author Rock
 * @date 2019/03/21
 */

namespace app\common\model;

use think\Db;
use think\Model;
use think\Exception;

class UserSign extends Model{


    // config 名称
    public $conf_name = 'user_sign_rule';
    // config 
    public $conf_inc_type = 'user_sign_rule';
    // 网站设置
    public $config = [];
    // 签到送佣金设置
    public $sign_conf = [];
    // 签到送佣金开关 0 关闭  1 开启
    public $sign_on_off = 0;
    // 签到送佣金额度
    public $sign_integral = 0;
    // 连续签到送佣金开关 0 关闭 1 开启
    public $continued_on_off = 0;
    // 连续签到送佣金规则
    public $rule = [];
    // 会员ID
    public $userid = '';

    function __construct(){
        // 实例化时，初始化类
        $this->custo_init();
    }

    // 自定义初始化
    public function custo_init(){
        $config = Db::query("select * from `tp_config` where `name` = '".$this->conf_inc_type."' and `inc_type` = '".$this->conf_inc_type."'");
        if($config){
            $config = $config[0];
            $this->sign_conf = $config['value'] = json_decode($config['value'],true);
            $this->sign_on_off = $config['value']['sign_on_off'];
            $this->sign_integral = $config['value']['sign_integral'];
            $this->continued_on_off = $config['value']['continued_on_off'];
            $this->rule = $config['value']['rule'];
            $this->config = $config;
        }else{
            echo "错误：请检查系统管理后台是否已经设置了相关信息";exit;
        }
    }

    // 签到是否开启
    function check_sign(){
        return $this->sign_on_off ? true : false;
    }
    
    // 连续签到是否开启
    function check_continued(){
        return $this->continued_on_off ? true : false;
    }

    // 获取用户信息
    function user_info($user_id){
        $info = Db::name('users')->field('')
    }
    // 签到
    function sign($pram = ''){
        if(is_array($pram)){
            $user_id = intval($pram['user_id']);
        }else{
            $user_id = intval($pram);
        }
        if(!intval($user_id)){
            return '错误：请传入 user_id';
        }
        echo $user_id;
    }













}
