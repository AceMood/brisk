<?php

/**
 * @class BriskWebPage
 * @file 渲染页面的抽象类
 * @author AceMood
 * @email zmike86@gmail.com
 */

//---------------

abstract class BriskWebPage implements BriskWebPageInterface {

  // 页面标题
  protected $title = '';

  // 页面渲染模式
  protected $mode = null;

  // 页面的浏览设备分类, pc或mobile
  protected $device = DEVICE_MOBILE;

  // 页面需然渲染的分片id
  protected $pageletIds = array();

  // 页面分片的部件
  protected $pagelets = array();

  // 当前请求页面关联的response对象
  protected $response = null;

  function __construct($title = '', $device = DEVICE_MOBILE) {
    $this->setTitle($title)->setDevice($device);
    if (BriskUtils::isAjaxPipe()) {
      $this->mode = RENDER_AJAXPIPE;
      $this->setPageletIds($_GET['pagelets']);
      $this->response = new BriskAjaxResponse();
    } else {
      $this->mode = RENDER_NORMAL;
      $this->response = BriskAPI::staticResourceResponse();
    }
  }

  function addMetadata($metadata) {
    $this->response->addMetadata($metadata);
    return $this;
  }

  function setMode($mode) {
    if ($mode === RENDER_AJAXPIPE) {
      $this->mode = $mode;
    } else if ($mode === RENDER_BIGPIPE) {
      $this->mode = $mode;
    } else {
      $this->mode = RENDER_NORMAL;
    }
    return $this;
  }

  function getMode() {
    return $this->mode;
  }

  function setDevice($device) {
    if (in_array($device, array(
      DEVICE_PC, DEVICE_MOBILE
    ))) {
      $this->device = $device;
    }
    return $this;
  }

  function getDevice() {
    return $this->device;
  }

  function setTitle($title) {
    $this->title = (string)hsprintf($title);
    return $this;
  }

  function getTitle() {
    return $this->title;
  }

  function setPageletIds($ids) {
    if (!is_array($ids)) {
      $ids = array($ids);
    }
    foreach ($ids as $id) {
      $this->pageletIds[] = $id;
    }
    return $this;
  }

  function getPageletIds() {
    return $this->pageletIds;
  }

  function setPrintType($type) {
    // 这个方法主要用于测试打印资源表的效果
    // 一般不需要手动调用
    if (isset($this->response)) {
      $this->response->setPrintType($type);
    }
  }

  function getPrintType() {
    if (isset($this->response)) {
      return $this->response->getPrintType();
    }
  }

  function getResponseObject() {
    // 提供获取私有reponse的方法, 方便调用设置cdn等功能
    return $this->response;
  }

  /**
   * 渲染期间加载对应的部件. 正常渲染则直接输出部件html内容, 否则记录页面部件
   * @param BriskPagelet $pagelet
   * @return BriskSafeHTML|$this
   */
  function loadPagelet($pagelet) {
    $pagelet->setParentView($this);
    if ($this->mode === RENDER_NORMAL) {
      return $pagelet->renderAsHTML();
    } else {
      $this->pagelets[$pagelet->getId()] = $pagelet;
      return $this;
    }
  }

  function getPagelets() {
    return $this->pagelets;
  }

  function renderResourcesOfType($type) {
    return $this->response->renderResourcesOfType($type);
  }

  function render() {
    $html = '';
    switch ($this->mode) {
      case RENDER_AJAXPIPE:
        // 这里不需要加载页面全局的资源, 不再调用`willRender`
        $html = $this->renderAsJSON();
        break;
      case RENDER_NORMAL:
        $this->willRender();
        $html = $this->renderAsHTML();
        break;
    }

    return $html;
  }

  /**
   * 渲染页面成html
   * @return string
   * @throws Exception
   */
  protected function renderAsHTML() {
    return (string)hsprintf(
      $this->getTemplateString(),
      phutil_escape_html($this->title),
      $this->response->renderResourcesOfType('css'),
      new PhutilSafeHTML(''),
      $this->response->renderResourcesOfType('js')
    );
  }

  /**
   * 渲染页面成json
   * @return array
   * @throws Exception
   */
  protected function renderAsJSON() {
    $res = array(
      'payload' => array()
    );

    //挑选需要渲染的部件
    foreach ($this->pagelets as $pageletId) {
      if (!isset($this->getWidgets()[$pageletId])) {
        throw new Exception(pht(
          'No widget with id %s found in %s',
          $pageletId,
          __CLASS__
        ));
      }

      $widget = $this->getWidgets()[$pageletId];

      $res['payload'][$pageletId] = $widget->renderAsJSON();
      $res['js'] = $this->response->renderResourcesOfType('js');
      $res['css'] = $this->response->renderResourcesOfType('css');
      $res['script'] = $this->response->produceScript();
      $res['style'] = $this->response->produceStyle();
    }

    // 需要元数据但不需要behavior
    $metadata = $this->response->getMetadata();
    if (!empty($metadata)) {
      $res['metadata'] = $metadata;
    }

    return json_encode($res);
  }

  /**
   * 获取默认的页面模板,可在子类复写
   * @return string
   */
  protected function getTemplateString() {
    return
<<<EOTEMPLATE
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>%s</title>
    %s
</head>
<body>%s</body>
%s
</html>
EOTEMPLATE;
  }

  // 渲染前触发, 子类可重写, 一般是加载关键资源
  protected function willRender() {}
}