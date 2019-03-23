<?php
namespace app\admin\validate;
use think\Validate;
class AgentLevel extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:agent_level'],
        ['level', 'require|unique:agent_level'],
        ['ind_per', 'require'],
        ['agent_per', 'require'],
        ['team_nums', 'require'],
    ];
    //错误信息
    protected $message  = [
        'level_name.require'    => '名称必填',
        'level_name.unique'     => '已存在相同等级名称',
        'level.require'    => '级别必填',
        'level.unique'     => '已存在相同等级级别',
        'ind_per.require'    => '个人业绩必填',
        'agent_per.require'    => '团队业绩必填',
        'team_nums.require'    => '团队同级人数必填',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'level_name'    =>'require|unique:agent_level,level_name^level_id',
        ],
    ];
}