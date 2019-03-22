<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use app\common\model\GoodsProfit;
use think\Db;
use think\Request;

class ProfitShare extends MobileBase
{
    public function __construct()
    {

    }
    public function profitTaking(){
        $goodsProfit=new GoodsProfit();
        //获取当天利润
        $today_profit=$goodsProfit->get_goods_profit();
        //获取合伙人个数   默认合伙人等级是5
        $partners=$goodsProfit->get_partners_num(5);
        //获取所有合伙人uid用于记录
        $partnersIds=$goodsProfit->get_all_partners(5);
        if($partners==0){
            $data['msg']='暂时没有合伙人';
            //写入记录表
        }
        //本日分红利润每人  暂定总利润的  2%
        $partProfit=$today_profit*2/100/$partners;
        //写入记录表
        $data=array();
        // 启动事务
        Db::startTrans();
        try {
            foreach($partnersIds as $key=>$value){
                $data['uid']=$value;
                $data['bonus_money']=$partProfit;
                $data['today_population']=$partners;
                $data['today_profit']=$today_profit;
                $data['today_ratio']=2;
                $data['add_time']=time();
                M('tp_profit_dividend_log')->insert($data);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

    }
}
