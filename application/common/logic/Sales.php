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
 * 返佣类逻辑
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
			$parents_id = array_filter($parents_id);  //去除0
			
			$this->cash_unlock($parents_id);	//提现解锁
			$is_repeat = $this->repeat_buy();
			
			//是否重复购买
			if ($is_repeat) {
				$reward = $this->repeat_reward($parents_id,$all_level);
			} else {
				$reward = $this->reward($parents_id,$user_level);
			}

			$this->team_bonus();	//团队奖励
			
			return $reward;
		} else {
			return array('msg'=>"该用户没有上级",'code'=>0);
		}
	}

	//是否重复购买
	public function repeat_buy()
	{
		$is_repeat = false;
		$order_num = 0;
		// $order_num = Db::name('order_goods')->alias('goods')
		// 			 ->distinct(true)
		// 			 ->join('order order','goods.order_id = order.order_id')
		// 			 ->where(['goods.goods_id'=>$this->goods_id,'order.user_id'=>$this->user_id])
		// 			 ->count();

		$order_goods = M('order_goods')->where(['goods_id'=>$this->goods_id])->select();
		if ($order_goods) {
			$ids = array_column($order_goods,'order_id');
			$order_num = M('order')->where('user_id',$this->user_id)->where('order_id','in',$ids)->count();
		}
		
		if ($order_num > 1) {
			$is_repeat = true;
		}
		return $is_repeat;
	}

	//第一次购买奖励
	public function reward($parents_id,$user_level)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}

		$order = $order['data'];
		
		$parents_id = array_reverse($parents_id);	//按原数组倒序排列
		$all_user = $this->all_user($parents_id);	//获取所有用户信息
		$level = $this->get_level();				//获取等级信息
		
		$comm = $this->get_goods_prize($order_num);
		$basic_reward = $comm['basic'];  //直推奖励
		$poor_prize = $comm['poor_prize'];//极差奖励
		$first_layer = $comm['first_layer'];//同级一层奖励
		$second_layer = $comm['second_layer'];//同级二层奖励
		
		if(is_array($basic_reward)){
			ksort($basic_reward );	//按键值升序排列
		}
		if (is_array($poor_prize)) {
			ksort($poor_prize);
		}
		
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
						$money = $first_layer[$value['distribut_level']] * $order['goods_num'];
						break;
					case 2:
						$money = $second_layer[$value['distribut_level']] * $order['goods_num'];
						break;
					default:
						break;
				}
				
				$is_prize = true;
				$msg = "同级奖 ".$money."（元），商品：".$order['goods_num']." 件";
			}
			//极差奖
			if ($user_level < $value['distribut_level']) {
				$layer = 0;

				//基本奖励已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
				}
				
				reset($poor_prize);	//重置数组指针
				
				//计算极差奖金
				while(list($k1,$v1) = each($poor_prize)){
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
				$msg = "极差奖 ".$money."（元），商品：".$order['goods_num']." 件";
			}
			if (!$money) {
				continue;
			}
			
			$user_money = $money+$value['user_money'];
			$distribut_money = $money+$value['distribut_money'];
			
			M('users')->where('user_id',$value['user_id'])->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);

			$this->writeLog($value['user_id'],$money,$order['order_sn'],$msg,$order['goods_num'],1,false);
		}
		
		return array('code'=>1);

	}

	//重复购买奖励
	public function repeat_reward($parents_id,$user_level)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}

		$order = $order['data'];
		
		$parents_id = array_reverse($parents_id);	//按原数组倒序排列
		$all_user = $this->all_user($parents_id);	//获取所有用户信息
		$level = $this->get_level();				//获取等级信息
		
		$comm = $this->get_goods_prize($order_num);
		$basic_reward = $comm['basic'];  //直推奖励
		$poor_prize = $comm['poor_prize'];//极差奖励
		$first_layer = $comm['first_layer'];//同级一层奖励
		$second_layer = $comm['second_layer'];//同级二层奖励
		
		if(is_array($basic_reward)){
			ksort($basic_reward );	//按键值升序排列
		}
		if (is_array($poor_prize)) {
			ksort($poor_prize);
		}
		
		$layer = 0;
		$msg = "";
		$is_prize = false;
		$have_prize = false;
		
		foreach ($all_user as $key => $value) {
			$money = 0;
			// //没有等级没有奖励
			// if ($value['distribut_level'] <= 0) {
			// 	continue;
			// }
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
			if (($user_level == $value['distribut_level']) && $is_prize) {
				$layer ++;
				//超过设定层数没有奖励
				if ($layer > 2) {
					continue;
				}
				
				switch($layer){
					case 1:
						$money = $first_layer[$user_level] * $order['goods_num'];
						break;
					case 2:
						$money = $second_layer[$user_level] * $order['goods_num'];
						break;
					default:
						break;
				}
				$is_prize = true;
				$msg = "重复购买同级奖 ".$money."（元），商品：".$order['goods_num']." 件";
			}
			//极差奖
			if ($user_level < $value['distribut_level']) {
				$layer = 0;

				//基本奖励已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
				}
				
				reset($poor_prize);	//重置数组指针
				
				//计算极差奖金
				while(list($k1,$v1) = each($poor_prize)){
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
				$msg = "重复购买极差奖 ".$money."（元），商品：".$order['goods_num']." 件";
			}
			if (!$money) {
				continue;
			}

			if (!$have_prize) {
				$my_prize = $money;
				if ($my_prize > 0) {
					M('users')->where('user_id',$this->user_id)->setInc('user_money',$my_prize);
					M('users')->where('user_id',$this->user_id)->setInc('distribut_money',$my_prize);
					$msg = "重复购买奖励 ".$my_prize."（元），商品：".$order['goods_num']." 件";
					$this->writeLog($this->user_id,$my_prize,$order['order_sn'],$msg,$order['goods_num'],1,false);
				}

				$have_prize = true;
			}
			
			$user_money = $money+$value['user_money'];
			$distribut_money = $money+$value['distribut_money'];
			
			M('users')->where('user_id',$value['user_id'])->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);

			$this->writeLog($value['user_id'],$money,$order['order_sn'],$msg,$order['goods_num'],1,false);
		}
		
		return array('code'=>1);

	}

	//团队奖励
	public function team_bonus()
	{
		$user_id = $this->user_id;
		$order_id = $this->order_id;
		$goods_id = $this->goods_id;

		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}
		$order = $order['data'];

		$goods = $this->goods();
		if ($goods['code'] == 0) {
			return $goods;
		}
		if ($goods['goods']['is_team_prize'] != 1) {
			return ['code'=>0,'msg'=>"该商品不是团队奖励商品"];
		}
		
		$first_leader = M('users')->where('user_id',$user_id)->value('first_leader');
		$leader = M('users')->where('user_id',$first_leader)->find();
		if (!$leader) {
			return ['code'=>0,'msg'=>"该用户没有上级"];
		}

		$per = M('agent_performance_log')->where(['user_id'=>$user_id,'order_id'=>$order_id])->find();
		$level = $this->get_level();

		$money = $per['money'] * ($level[$leader['distribut_level']]['team_bonus'] / 100);
		$money = rand($money,2);

		if(!$money){
			return ['code'=>0];
		}

		$user_money = $money + $leader['user_money'];
		$distribut_money = $money + $leader['distribut_money'];
		$msg = "团队分红 ". $money . "（元），商品：".$order['goods_num']." 件";

		$bool = M('users')->where('user_id',$first_leader)->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);
		$this->writeLog($first_leader,$money,$order['order_sn'],$msg,$order['goods_num'],2,true);
		$result = $bool ? array('code' => 1) : array('code'=>0);

		return $result;
	}
	
	//获取返佣配置信息
	public function get_goods_prize($order_num)
	{
		$goods_prize = M('goods')->where('goods_id',$this->goods_id)->value('goods_prize');
		$ids = json_decode($goods_prize,true);
		
		if($order_num > 1){
			$fields = 'level,self_buying as basic,self_poor_prize as poor_prize,self_reword as first_layer,self_reword2 as second_layer';
		} else {
			$fields = 'level,reward as basic,poor_prize,same_reword as first_layer,same_reword2 as second_layer';
		}
		$comm = M('goods_commission')->where('id','in',$ids)->column($fields);
		$result['basic'] = array();
		$result['poor_prize'] = array();
		$result['first_layer'] = array();
		$result['second_layer'] = array();
		
		if($comm){
			foreach($comm as $key => $value){
				$result['basic'][$key] = $value['basic'];
				$result['poor_prize'][$key] = $value['poor_prize'];
				$result['first_layer'][$key] = $value['first_layer'];
				$result['second_layer'][$key] = $value['second_layer'];
			}
		}
		
		return $result;
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
		$level = M('agent_level')->column('level,team_bonus');

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
		$goods = M('goods')->where('goods_id',$this->goods_id)->field('goods_id,is_team_prize')->find();

		if (!$goods) {
			return array('msg'=>"没有该商品的信息",'code'=>0);
		}

		return array('goods'=>$goods,'code'=>1);
	}

	//记录日志
	public function writeLog($user_id,$money,$order_sn,$desc,$num,$type = 0,$is_team)
	{
		$data = array(
			'user_id'=>$this->user_id,
			'to_user_id'=>$user_id,
			'money'=>$money,
			'order_sn'=>$order_sn,
			'order_id'=>$this->order_id,
			'goods_id'=>$this->goods_id,
			'num'=>$num,
			'type'=>$type,
			'create_time'=>time(),
			'desc'=>$desc
		);

		$bool = M('distrbut_commission_log')->insert($data);

		if($bool && !$is_team){
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