<?php
namespace app\admin\validate;
use think\Validate;
class AgentLevel extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:agent_level'],
        ['level', 'require|number|unique:agent_level'],
        ['ind_goods_sum', 'require|number'],
        ['agent_goods_sum', 'require|number'],
        ['team_nums', 'require|number'],
        ['same_reword', 'require|number'],
        ['same_reword2', 'require|number'],
        // ['layer', 'require|number'],
    ];
    //错误信息
    protected $message  = [
        'level_name.require'    => '名称必填',
        'level_name.unique'     => '已存在相同等级名称',
        'level.require'    => '级别必填',
        'level.unique'     => '已存在相同等级级别',
        'level.number'    => '级别必须是数字',
        'ind_goods_sum.require'    => '个人业绩必填',
        'ind_goods_sum.number'    => '个人业绩必须是数字',
        'agent_goods_sum.require'    => '团队业绩必填',
        'agent_goods_sum.number'    => '团队业绩必须是数字',
        'team_nums.require'    => '团队同级人数必填',
        'team_nums.number'    => '团队同级人数是数字',
        'same_reword.require'  => '同级奖励(一代)必填',
        'same_reword.number'    => '同级奖励(一代)必须是数字',
        'same_reword2.require'  => '同级奖励(二代)必填',
        'same_reword2.number'    => '同级奖励(二代)必须是数字',
        // 'layer.require'     => '同级奖励层数必填',
        // 'layer.number'      => '同级奖励层数必须是数字',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'level_name'    =>'require|unique:agent_level,level_name^level_id',
            'level'    =>'require|number|unique:agent_level,level_name^level_id',
            'ind_goods_sum'    =>'require|number',
            'agent_goods_sum'    =>'require|number',
            'team_nums'    =>'require|number',
            'same_reword'    =>'require|number',
            'same_reword2'    =>'require|number',
            // 'layer'    =>'require|number',
        ],
    ];
}