<?php

/**
 * @author: pc
 * Date: 2019-3-25
 */

namespace app\common\logic;

use think\Model;
use think\Db;

/**
 * 业绩类逻辑
 * Class PerformanceLogic
 * @package app\common\logic
 */
class PerformanceLogic extends Model
{
	//业绩
	public function per($order_id)
	{
		$order = M('order')->where('order_id',$order_id)->find();
		if (!$order) {
			return false;
		}

		$goods_num = M('order_goods')->where('order_id',$order_id)->where('order_sn',$order['order_sn'])->sum('goods_num');
		$price = $order['goods_price'];
		$user_id = $order['user_id'];
		$order_sn = $order['order_sn'];
		
		$is_per = M('agent_performance')->where('user_id',$order['user_id'])->find();

		$log = array(
			'money'=>$price,
			'goods_num'=>$goods_num,
			'order_id'=>$order_id,
			'create_time'=>Date('Y-m-d H:m:s')
		);

		//购买者添加业绩
		if ($is_per) {
			$ind_per = array(
				'ind_per'=>$is_per['ind_per']+$price,
				'ind_goods_sum'=>$is_per['ind_goods_sum']+$goods_num,
				'update_time'=>Date('Y-m-d H:m:s')
			);

			$bool = M('agent_performance')->where('user_id',$user_id)->update($ind_per);
		} else {
			$ind_per = array(
				'user_id'=>$user_id,
				'ind_per'=>$price,
				'ind_goods_sum'=>$goods_num,
				'create_time'=>Date('Y-m-d H:m:s'),
				'update_time'=>Date('Y-m-d H:m:s')
			);

			$bool = M('agent_performance')->insert($ind_per);
		}
		
		//个人业绩日志
		if ($bool) {
			$log['user_id'] = $user_id;
			$note = '订单编号为'.$order_sn.'的业绩';
			$this->per_log($log,$note);
		} else {
			$log['user_id'] = $user_id;
			$note = '订单编号为'.$order_sn.'的业绩增加失败';
			
			$this->per_log($log,$note);
		}

		$id_list = M('users')->where('user_id',$user_id)->value('parents');
		$id_list = explode(',', $id_list);
		$new_list = array_filter($id_list);
		
		foreach ($new_list as $key => $value) {
			$is_team = M('agent_performance')->where('user_id',$value)->find();
			
			//团队者添加业绩
			if ($is_team) {
				$team_per = array(
					'agent_per'=>$is_team['agent_per']+$price,
					'agent_goods_sum'=>$is_team['agent_goods_sum']+$goods_num,
					'update_time'=>Date('Y-m-d H:m:s')
				);
				
				$bool1 = M('agent_performance')->where('user_id',$value)->save($team_per);
			} else {
				$team_per = array(
					'user_id'=>$value,
					'agent_per'=>$price,
					'agent_goods_sum'=>$goods_num,
					'create_time'=>Date('Y-m-d H:m:s'),
					'update_time'=>Date('Y-m-d H:m:s')
				);
				
				$bool1 = M('agent_performance')->insert($team_per);
			}

			$log['user_id'] = $value;

			//团队业绩日志
			if ($bool1) {
				$note = '订单编号为'.$order_sn.'的业绩';

				$this->per_log($log,$note);
			} else {
				$note = '订单编号为'.$order_sn.'的业绩添加失败';

				$this->per_log($log,$note);
			}
		}
	}

	//业绩日志
	public function per_log($data,$note)
	{
		$data['note'] = $note;

		M('agent_performance_log')->insert($data);
	}
}