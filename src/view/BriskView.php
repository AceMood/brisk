<?php

/**
 * Class BriskView
 * 视图的基类, 可以是整页视图, 也可以是组件视图
 */
abstract class BriskView extends Phobject {

    protected $mode;
    protected $id;

    function __construct($id = null, $mode) {
        $this->mode = $mode;
        $this->id = $id;
    }

    /**
     * 渲染本视图
     * @return mixed
     */
    abstract protected function render();
}