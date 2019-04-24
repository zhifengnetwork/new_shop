<?php
/**
*	tpshop
*  ---------------------------------------------------------------------------------------
*	author: pc
*	date: 2019-3-25
**/

namespace app\common\logic;

use think\Model;
use think\Db;
use think\Cache;

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
		$user_level = $user['distribut_level'];
		if ($user['parents']) {
			$parents_id = explode(',', $user['parents']);
			$parents_id = array_filter($parents_id);  //去除0,倒序排列
			
			$this->cash_unlock($parents_id);	//提现解锁
			
			$reward = $this->reward($parents_id,$user_level);
			return $reward;
		} else {
			return array('msg'=>"该用户没有上级",'code'=>0);
		}
	}

	//奖励
	public function reward($parents_id,$user_level)
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
		
		$parents_id = array_reverse($parents_id);	//按原数组倒序排列
		$all_user = $this->all_user($parents_id);	//获取所有用户信息
		$level = $this->get_level();				//获取等级信息

		$basic_reward = json_decode($goods['goods']['basic_reward'],true);
		$each_reward = json_decode($goods['goods']['each_reward'],true);
		
		if(is_array($basic_reward)){
			ksort($basic_reward );	//按键值升序排列
		} else {
			$basic_reward = array();
		}
		if (is_array($each_reward)) {
			ksort($each_reward);
		} else {
			$each_reward = array();
		}
		
		// $user_level = 0;
		$min_level = 0;
		$layer = 0;
		$msg = "";
		$is_prize = false;
		
		foreach ($all_user as $key => $value) {
			$money = 0;
			//没有等级没有奖励
			if ($value['distribut_level'] <= 0) {
				continue;
			}
			//账号冻结了没有奖励
			if ($value['is_lock'] == 1) {
				continue;
			}
			//不是分销商不奖励
			if ($value['is_distribut'] != 1) {
				continue;
			}
			
			//等级比下级低没有奖励
			if ($user_level > $value['distribut_level']) {
				continue;
			}
			
			//平级奖
			if ($user_level == $value['distribut_level']) {
				$layer ++;
				//超过设定层数没有奖励
				if ($layer > 2) {
					continue;
				}
				
				switch($layer){
					case 1:
						$money = $level[$user_level]['same_reword'] * $order['goods_num'];
						break;
					case 2:
						$money = $level[$user_level]['same_reword2'] * $order['goods_num'];
						break;
					default:
						break;
				}
				
				$msg = "同级奖励 ".$money."（元）";
			}
			//极差奖
			if ($user_level < $value['distribut_level']) {
				$layer = 0;

				//基本奖励已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
				}
				
				reset($each_reward);	//重置数组指针
				
				//计算极差奖金
				while(list($k1,$v1) = each($each_reward)){
					if ($user_level >= $k1) {
						continue;
					}
					if ($k1 <= $value['distribut_level']) {
						$v1 = $v1 ? $v1 : 0;
						$money += $v1 * $order['goods_num'];
						continue;
					}
					break;
				}
				
				$user_level = $value['distribut_level'];
				$msg = "级别利润 ".$money."（元），商品：".$order['goods_num']."件";
			}
			if (!$money) {
				continue;
			}
			
			$user_money = $money+$value['user_money'];
			$distribut_money = $money+$value['distribut_money'];
			
			M('users')->where('user_id',$value['user_id'])->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);

			$this->writeLog($value['user_id'],$money,$order['order_sn'],$msg,$order['goods_num']);
		}
		
		return array('code'=>1);

	}

	//获取所有用户信息
	public function all_user($parents_id)
	{
		$all = M('users')->where('user_id','in',$parents_id)->column('user_id,first_leader,distribut_level,is_lock,user_money,distribut_money,is_distribut');
		$result = array();

		foreach ($parents_id as $key => $value) {
			array_push($result, $all[$value]);
		}
		return $result;
	}

	//获取等级信息
	public function get_level()
	{
		$level = M('agent_level')->column('level,same_reword,same_reword2');

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
						->where('goods_id',$this->goods_id)
						->find();
		
		if (!$order_goods) {
			return array('msg'=>"没有该商品的订单信息",'code'=>0);
		}

		$data = array('goods_id'=>
			$this->goods_id,'goods_num'=>$order_goods['goods_num'],'order_sn'=>$order['order_sn']);

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
	public function writeLog($user_id,$money,$order_sn,$desc,$num)
	{
		$data = array(
			'user_id'=>$this->user_id,
			'to_user_id'=>$user_id,
			'money'=>$money,
			'order_sn'=>$order_sn,
			'order_id'=>$this->order_id,
			'goods_id'=>$this->goods_id,
			'num'=>$num,
			'create_time'=>time(),
			'desc'=>$desc
		);

		$bool = M('distrbut_commission_log')->insert($data);

		if($bool){
			//分钱记录
			$data = array(
				'order_id'=>$this->order_id,
				'user_id'=>$user_id,
				'status'=>1,
				'goods_id'=>$this->goods_id,
				'money'=>$money,
				'add_time'=>Date('Y-m-d H:m:s')
			);
			M('order_divide')->add($data);
		}
		
		return $bool;
	}

	/**
	 * 提现解锁
	 */
	public function cash_unlock($parents_id)
	{
		if (!$parents_id) {
			return false;
		}

		$is_cash = tpCache('cash.goods_id');
		
		if (intval($is_cash) == $this->goods_id) {
			M('users')->where('user_id','in',$parents_id)->update(['is_cash'=>1]);
		}
	}
}