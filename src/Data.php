<?php
/**
 *  +----------------------------------------------------------------------
 *  | Created by  hahadu
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2020. [hahadu] All rights reserved.
 *  +----------------------------------------------------------------------
 *  | SiteUrl: https://github.com/hahadu
 *  +----------------------------------------------------------------------
 *  | Author: hahadu <582167246@qq.com>
 *  +----------------------------------------------------------------------
 *  | Date: 2020/9/16 下午3:07
 *  +----------------------------------------------------------------------
 *  | Description:  数据处理类
 *  +----------------------------------------------------------------------
 **/



namespace Hahadu\DataHandle;
use Hahadu\Collect\Collection;

final class Data
{
    /****
     * @var Collection
     */
    private $items;

    /*****
     * @param array|Collection $data
     */
    public function __construct($data = [])
    {
        //dump($data);
        if($data instanceof Collection){
            $this->items = $data;
        }else{
            $this->items = new Collection($data);
        }
    }

    static public function make($data){
        return new self($data);
    }

    /**
     * 返回多层栏目
     * @param array $data 操作的数组
     * @param int $pid 一级PID的值
     * @param string $html 栏目名称前缀
     * @param string $fieldPri 唯一键名，如果是表则是表的主键
     * @param string $fieldPid 父ID键名
     * @param int $level 不需要传参数（执行时调用）
     * @return Collection
     */
    public function channelLevel($pid = 0, $html = "&nbsp;", $fieldPri = 'cid', $fieldPid = 'pid', $level = 1)
    {
        if ($this->items->isEmpty()) {
            return $this->items;
        }
        $arr = [];
        foreach ($this->items as $item){
            if ($item[$fieldPid] == $pid) {
                $arr[$item[$fieldPri]] = $item;
                $arr[$item[$fieldPri]]['_level'] = $level;
                $arr[$item[$fieldPri]]['_html'] = str_repeat($html, $level - 1);
                $arr[$item[$fieldPri]]['_child'] = $this->channelLevel($item[$fieldPri], $html, $fieldPri, $fieldPid, $level + 1);
            }
        }
        return new Collection($arr);
    }

    /**
     * 获得所有子栏目
     * @param mixed $data 栏目数据
     * @param int $pid 操作的栏目
     * @param string $html 栏目名前字符
     * @param string $fieldPri 表主键
     * @param string $fieldPid 父id
     * @param int $level 等级
     * @return Collection
     */
    public function channelList( $pid = 0, $html = "&nbsp;", $fieldPri = 'cid', $fieldPid = 'pid', $level = 1)
    {
        $data = $this->_channelList( $pid, $html, $fieldPri, $fieldPid, $level);
        if ($data->isEmpty()){
            return $data;
        }
        foreach ($data as $n => $m) {
            if ($m['_level'] == 1)
                continue;
            $data[$n]['_first'] = false;
            $data[$n]['_end'] = false;
            if (!isset($data[$n - 1]) || $data[$n - 1]['_level'] != $m['_level']) {
                $data[$n]['_first'] = true;
            }
            if (isset($data[$n + 1]) && $data[$n]['_level'] > $data[$n + 1]['_level']) {
                $data[$n]['_end'] = true;
            }
        }
        //更新key为栏目主键
        $category = new Collection();
        $data->each(function ($item,$key)use($fieldPri,$category){
            $category->push($item,$item[$fieldPri]);
        });
        return $category;
    }

    /*****
     * 只供channelList方法使用
     * @param array|Collection $data
     * @param int $pid
     * @param string $html
     * @param string $fieldPri
     * @param string $fieldPid
     * @param int $level
     * @return Collection
     */
    private function _channelList( $pid = 0, $html = "&nbsp;", $fieldPri = 'cid', $fieldPid = 'pid', $level = 1)
    {
        if ($this->items->isEmpty()){
            return $this->items;
        }
        $arr = new Collection();
        $this->items->each(function ($item,$key)use($arr,$fieldPri,$fieldPid,$pid,$level,$html){
            $id = $item[$fieldPri];
            if ($item[$fieldPid] == $pid) {
                $v['_level'] = $level;
                $v['_html'] = str_repeat($html, $level - 1);
                $arr->push($v);
                $tmp = $this->_channelList( $id, $html, $fieldPri, $fieldPid, $level + 1);
                $arr->merge($tmp);
            }

        });
        return $arr;
    }

    /**
     * 获得树状数据
     * @param array|Collection $data 数据
     * @param string $title 字段名
     * @param string $fieldPri 主键id
     * @param string $fieldPid 父id
     * @return Collection
     */
    public function tree( $title, $fieldPri = 'cid', $fieldPid = 'pid')
    {

        $arr = $this->channelList( 0, '', $fieldPri, $fieldPid);
        if ( $arr->isEmpty()){
            return $arr;
        }
        $arr = $arr->each(function ($v,$k)use($title,$arr){
            $str = "";
            if ($v['_level'] > 2) {
                for ($i = 1; $i < $v['_level'] - 1; $i++) {
                    $str .= " &emsp; │";
                }
            }
            if ($v['_level'] != 1) {
                $t = $title ? $v[$title] : "";
                if (isset($arr[$k + 1]) && $arr[$k + 1]['_level'] >= $v['_level']) {
                    $v['_name'] = $str . " &emsp; ├─ " . $v['_html'] . $t;
                } else {
                    $v['_name'] = $str . " &emsp; └─ " . $v['_html'] . $t;
                }
            }else {
                $v['_name'] = $v[$title];
            }
            return $v;
        });
        //设置主键为$fieldPri
        $data = new Collection();
        $arr->each(function ($item,$key)use($fieldPri,$data){
            $data->push($item,$item[$fieldPri]);
        });
        return $data;
    }

    /**
     * 获得所有父级栏目
     * @param int $sid 子栏目
     * @param string $fieldPri 唯一键名，如果是表则是表的主键
     * @param string $fieldPid 父ID键名
     * @return Collection
     */
    public function parentChannel($sid, $fieldPri = 'cid', $fieldPid = 'pid')
    {
        if ($this->items->isEmpty()) {
            return $this->items;
        } else {
            $collect = Collection::make();
            foreach ($this->items as $v) {
                if ($v[$fieldPri] == $sid) {
                    $collect->push($v);
                    $_n = $this->parentChannel($v[$fieldPid], $fieldPri, $fieldPid);
                    if (!empty($_n)) {
                        $collect->merge($_n);
                    }
                }
            }
            return $collect;
        }
    }

    /**
     * 判断$s_cid是否是$d_cid的子栏目
     * @param int $sid 子栏目id
     * @param int $pid 父栏目id
     * @param string $fieldPri 主键
     * @param string $fieldPid 父id字段
     * @return bool
     */
    public function isChild( $sid, $pid, $fieldPri = 'cid', $fieldPid = 'pid')
    {
        $_data = $this->channelList( $pid, '', $fieldPri, $fieldPid);
        foreach ($_data as $c) {
            //目标栏目为源栏目的子栏目
            if ($c[$fieldPri] == $sid)
                return true;
        }
        return false;
    }

    /**
     * 检测是不否有子栏目
     * @param string $cid 要判断的栏目cid
     * @param string $fieldPid 父id表字段名
     * @return bool
     */
    public function hasChild( $cid, $fieldPid = 'pid')
    {
        foreach ($this->items as $d) {
            if ($d[$fieldPid] == $cid) return true;
        }
        return false;
    }

    /**
     * 递归实现迪卡尔乘积
     * @param array $tmp
     * @return array
     */
    public function descarte( $tmp = array())
    {
        static $n_arr = array();

        foreach ($this->items->shift() as $v) {
            $tmp[] = $v;
            if ($this->items) {
                $this->descarte($this->items, $tmp);
            } else {
                $n_arr[] = $tmp;
            }
            array_pop($tmp);
        }
        return $n_arr;
    }

}


