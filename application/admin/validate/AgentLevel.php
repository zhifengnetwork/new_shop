<?php
namespace app\admin\validate;
use think\Validate;
class AgentLevel extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:agent_level'],
    ];
    //错误信息
    protected $message  = [
        'level_name.require'    => '名称必填',
        'level_name.unique'     => '已存在相同等级名称',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'level_name'    =>'require|unique:agent_level,level_name^level_id',
        ],
    ];
}