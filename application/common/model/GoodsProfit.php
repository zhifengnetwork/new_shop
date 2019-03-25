<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;

use think\Db;
use think\Model;

class GoodsProfit extends Model
{
    //查询并计算当日的利润总和
    public function get_goods_profit(){
        //今日起始时间戳
        $todaytime=strtotime(date('Y-m-d 00:00:00',time()));
        return M('order_goods')->alias('og')->join('order o','og.order_id=o.order_id')->where('o.pay_time','>=',$todaytime)->field('sum(og.final_price-og.cost_price) total')->find();
    }
    //查询合伙人的个数   $level是要查询的等级
    public function get_partners_num($level){
        return M('users')->where(['distribut_level'=>$level,'is_lock'=>0])->count();
    }
    //查询所有分红的人
    public function get_all_partners($level){
        return M('users')->where(['distribut_level'=>$level,'is_lock'=>0])->column('user_id');
    }
    public function get_config($name){
        // 获取配置表
        $configs = Db::name('config')->field('name,value')->select();
        // 把配置项name转换成$configs['price_min1']['value']
        $configs = $this->arr2name($configs);
        return $configs[$name]['value'];
    }
//数组转换成[配置项名称]获取数据
    public function arr2name($data,$key=''){
        $return_data=array();
        if(!$data||!is_array($data)){
            return $return_data;
        }
        if(!$key){
            $key='name';
        }
        foreach($data as $dv){
            $return_data[$dv[$key]]=$dv;
        }
        return $return_data;
    }
}
