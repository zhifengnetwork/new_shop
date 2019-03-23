<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 */

namespace app\common\logic;

use think\Model;
use think\Db;

/**
 * 销售类逻辑
 * Class Sales
 * @package app\common\logic
 */
class Sales extends Model
{
	private $user_id; //用户id
	private $order_id;//订单id
	private $goods_id;//商品id

	public function __construct($user_id,$order_id,$goods_id)
	{	
		$this->user_id = $user_id;
		$this->order_id = $order_id;
		$this->goods_id = $goods_id;
	}

	public function sales()
	{
		$user = M('users')->where('user_id',$this->user_id)->find();
		if ($user['parents']) {
			$parents_id = explode(',', $user['parents']);
			$parents_id = array_filter($parents_id);  //去除0,倒序排列
			
			$reward = $this->reward($parents_id);
			return $reward;
		} else {
			return array('msg'=>"该用户没有上级",'code'=>0);
		}
	}

	//奖励
	public function reward($parents_id)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}

		$order = $order['data'];
		$goods = $this->goods();
		if ($goods['code'] != 1) {
			return $goods;
		}

		$all_user = $this->all_user($parents_id);
		$level = $this->get_level();

		$basic_reward = json_decode($goods['goods']['basic_reward'],true);
		$each_reward = json_decode($goods['goods']['each_reward'],true);
		
		if(is_array($basic_reward)){
			ksort($basic_reward );
		} else {
			$basic_reward = array();
		}
		if (is_array($each_reward)) {
			ksort($each_reward);
		} else {
			$each_reward = array();
		}
		
		$user_level = 0;
		$layer = 0;
		$msg = "";
		
		foreach ($all_user as $key => $value) {
			if ($value['distribut_level'] <= 0) {
				continue;
			}
			if ($value['is_lock'] == 1) {
				continue;
			}
			if ($user_level > $value['distribut_level']) {
				continue;
			}
			
			if ($user_level == $value['distribut_level']) {
				$layer ++;
				if ($layer > $level[$user_level]['layer']) {
					continue;
				}
				
				$money = $level[$user_level]['same_reword'];
				$msg = "同级奖励 ".$money."(元)";
			}
			if ($user_level < $value['distribut_level']) {
				$layer = 0;
				$user_level = $value['distribut_level'];
				$money = $basic_reward[$value['distribut_level']];
				
				while(list($k1,$v1) = each($each_reward)){
					if ($k1 <= $value['distribut_level']) {
						$money += $v1;
						continue;
					}
					break;
				}

				$msg = "级别利润 ".$money."(元)";
			}
			
			$bool = M('users')->where('user_id',$value['user_id'])->setInc('user_money',$money);
			
			if ($bool) {
				$this->writeLog($value['user_id'],$money,$order['order_sn'],$msg);
			}
		}
		return $money;
		return array('code'=>1);

	}

	//获取所有用户信息
	public function all_user($parents_id)
	{
		$all = M('users')->where('user_id','in',$parents_id)->column('user_id,first_leader,distribut_level,is_lock');
		$result = array();

		foreach ($parents_id as $key => $value) {
			array_push($result, $all[$value]);
		}
		return $result;
	}

	//获取等级信息
	public function get_level()
	{
		$level = M('agent_level')->column('level,same_reword,bonus,layer');

		return $level;
	}

	//订单信息
	public function order()
	{
		$order = M('order')->where('order_id',$this->order_id)->find();
		if (!$order) {
			return array('msg'=>"没有该商品的订单信息",'code'=>0);
		}
		
		$order_goods = M('order_goods')
						->where('order_id',$this->order_id)
						->where('order_sn',$order['order_sn'])
						->where('goods_id',$this->goods_id)
						->find();
		
		if (!$order_goods) {
			return array('msg'=>"没有该商品的订单信息",'code'=>0);
		}

		$data = array('goods_id'=>
			$order['goods_id'],'goods_num'=>$order_goods['goods_num'],'order_sn'=>$order['order_sn']);

		return array('data'=>$data,'code'=>1);
	}

	//商品信息
	public function goods()
	{
		$goods = M('goods')->where('goods_id',$this->goods_id)->field('goods_id,basic_reward,each_reward')->find();

		if (!$goods) {
			return array('msg'=>"没有该商品的信息",'code'=>0);
		}

		return array('goods'=>$goods,'code'=>1);
	}

	//记录日志
	public function writeLog($user_id,$money,$order_sn,$desc)
	{
		$data = array(
			'user_id'=>$user_id,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$order_sn,
			'order_id'=>$this->order_id
		);

		$bool = M('account_log')->insert($data);

		if($bool){
			//分钱记录
			$data = array(
				'order_id'=>$this->order_id,
				'user_id'=>$user_id,
				'status'=>1,
				'goods_id'=>$this->goods_id,
				'money'=>$money
			);
			M('order_divide')->add($data);
		}
		
		return $bool;
	}
}