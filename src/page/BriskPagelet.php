<?php

/**
 * @class BriskPagelet
 * @file 所有页面分片部件的基类. 同一个部件类的不同实例可在多个页面通过id,以及mode区分.
 *       WidgetView对不用渲染模式需要提供两个方法进行渲染,
 *       1. 顶级页面正常渲染, 部件提供renderAsHTML方法,
 *          依据初始化时指定的模式渲染, normal, bigrender 或者lazyrender
 *       2. 顶级页面通过ajaxpipe渲染, 部件提供renderAsJSON方法
 *       3. 目前pagelet不支持嵌套, 各pagelet都是平行的组件关系 todo
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

abstract class BriskPagelet implements BriskPageletInterface {

  // widget id, used to wrapper div or replace placeholder dom
  protected $id = '';

  // priority
  protected $priority = 0;

  // render mode
  protected $mode = null;

  // user-defined dom attributes
  protected $attributes = array();

  // parent pagelet or webpage
  protected $parentView = null;

  // dependent css, no matter external link or inline
  protected $dependentCss = array();

  // dependent js, no matter external link or inline
  protected $dependentJs = array();

  // Data object, negotiate with server-side
  protected $dataSource = null;

  // sub-pagelet collection
  protected $pagelets = array();

  // always true
  public function isPagelet() {
    return true;
  }

  public function __construct($id = '', $mode = null) {
    if (empty($id)) {
      $id = BriskUtils::generateUniqueId();
    }

    $this->setId($id)->setMode($mode);
  }

  public function setMode($mode) {
    if (in_array($mode, array(
      RENDER_BIGRENDER,
      RENDER_LAZYRENDER
    ))) {
      $this->mode = $mode;
    } else {
      $this->mode = RENDER_NORMAL;
    }
    return $this;
  }

  public function getMode() {
    return $this->mode;
  }

  public function setId($id) {
    $this->id = BriskDomProxy::escapeHtml($id);
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  public function setDomAttributes($attributes) {
    $this->attributes = $attributes;
    return $this;
  }

  public function getDomAttributes() {
    return $this->attributes;
  }

  /**
   * template string combine with server data, use `getTemplateString`
   * to generate html fragment.
   * @return string
   */
  public function produceHTML() {
    return (string)hsprintf(
      new BriskSafeHTML($this->getTemplateString())
    );
  }

  public function getDependentCss() {
    return $this->dependentCss;
  }

  public function getDependentJs() {
    return $this->dependentJs;
  }

  public function requireResource($name, $source_name = 'brisk') {
    // pagelet provide `requireResource` method so that we can record the
    // SR had been required. If call `require_static` directly we can also
    // load the SR but without ant record. We recommend use `requireResource($name, $ns)`
    // instead in a pagelet context.
    $this->recordDependentResource($name, $source_name);
    require_static($name, $source_name);
  }

  public function setDataSource($data) {
    $this->dataSource = $data;
  }

  public function getDataSource() {
    return $this->dataSource;
  }

  // 组件主动获取数据源. 保留这个方法作为bigpipe实现时的具体实现.
  // `fetchDataSource`调用后应直接调用render方法进行输出.
  public function fetchDataSource() {
    // todo
    ob_flush();
    flush();
  }

  public function setParentView($parent) {
    $this->parentView = $parent;
  }

  public function getParentView() {
    return $this->parentView;
  }

  public function inlineResource($name, $source_name = 'brisk') {
    // 部件中提供`inlineResource`的目的是可以记录每个部件依赖的静态资源.
    // 如果不调用实例方法, 而是直接调用`inline_static`可以加载资源, 但是不会记录
    // 本部件和资源的关系, 推荐使用`$pagelet->inlineResource($name, $ns)`.
    $this->recordDependentResource($name, $source_name);
    inline_static($name, $source_name);
  }

  public function getTopLevelView() {
    $parent = $this->getParentView();
    while (isset($parent) && isset($parent->isPagelet) && $parent->isPagelet()) {
      $parent = $parent->getParentView();
    }
    return $parent;
  }

  /**
   * Render the pagelet view
   * @return string
   */
  public function renderAsHTML() {
    $html = '';
    switch ($this->mode) {
      case RENDER_NORMAL:
        $this->willRender();
        $html = $this->produceHTML();
        break;
      case RENDER_BIGRENDER:
        // bigrender模式下也依赖于前端js库的实现, 具体做法就是取出textarea中的html片段,
        // 放到随后的div中并移除textarea元素. 这里面注意如果html片段中有行内script, 直接
        // 设置innerHTML是不行的, 所以`produceHTML`产出的html不应该包含行内script, 而
        // style是没有这个问题的.
        $this->willRender();
        $html = BriskDomProxy::tag(
          'textarea',
          array_merge(
            array(
              'class' => 'g_brisk_bigrender',
              'style' => 'display:none;',
              'data-bigrender' => 1,
              'data-pageletId' => $this->id
            ),
            $this->getDomAttributes()
          ),
          $this->produceHTML()
        );
        $html->appendHTML(BriskDomProxy::tag('div', array('id' => $this->id)));
        break;
      case RENDER_LAZYRENDER:
        // 此处将异步加载的js代码直接输出到textarea中. 具体实现依赖于浏览器端的js库,
        // 这里可以根据项目修改, 目前假设前端库提供`BigPipe.asyncLoad`方法.
        $html = BriskDomProxy::tag(
          'textarea',
          array_merge(
            array(
              'class' => 'g_brisk_lazyrender',
              'style' => 'display:none;'
            ),
            $this->getDomAttributes()
          ),
          hsprintf('BigPipe.asyncLoad({id: "%s"});', $this->id)
        );
        $html->appendHTML(BriskDomProxy::tag('div', array('id' => $this->id)));
        break;
    }

    return $html;
  }

  // record SR in current widget
  public function recordDependentResource($name, $source_name) {
    // confirm to exist
    $map = BriskResourceMap::getNamedInstance($source_name);
    $symbol = id($map->getNameMap())[$name];
    if (!isset($symbol)) {
      throw new Exception(pht(
        'No resource with name "%s" exists in source "%s"!',
        $name,
        $source_name
      ));
    }

    $resource_type = $map->getResourceTypeForName($name);
    switch ($resource_type) {
      case 'css':
        if (isset($this->dependentCss[$source_name]) &&
          !in_array($name, $this->dependentCss[$source_name])) {
          $this->dependentCss[$source_name][] = $name;
        }
        break;
      case 'js':
        if (isset($this->dependentJs[$source_name]) &&
          !in_array($name, $this->dependentJs[$source_name])) {
          $this->dependentJs[$source_name][] = $name;
        }
        break;
    }
  }

  // Triggered before rendering, overwritten by subclasses.
  // require SR in this method
  protected function willRender() {}

  // template string implemented by subclasses
  abstract function getTemplateString();
}