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

  public function __construct($title = '', $device = DEVICE_MOBILE) {
    $this->setTitle($title);
    if (BriskUtils::isAjaxPipe()) {
      $this->mode = RENDER_AJAXPIPE;
      $this->setPageletIds($_GET['pagelets']);
    } else {
      $this->mode = RENDER_NORMAL;
    }
    $this->response = BriskAPI::staticResourceResponse();
    $this->setDevice($device);
  }

  public function addMetadata($metadata) {
    $this->response->addMetadata($metadata);
    return $this;
  }

  public function setMode($mode) {
    if ($mode === RENDER_AJAXPIPE) {
      $this->mode = $mode;
    } else if ($mode === RENDER_BIGPIPE) {
      $this->mode = $mode;
    } else {
      $this->mode = RENDER_NORMAL;
    }
    return $this;
  }

  public function getMode() {
    return $this->mode;
  }

  public function setDevice($device) {
    if (in_array($device, array(
      DEVICE_PC, DEVICE_MOBILE
    ))) {
      $this->device = $device;
    }
    $this->response->setDeviceType($device);
    return $this;
  }

  public function getDevice() {
    return $this->device;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setPageletIds($ids) {
    if (!is_array($ids)) {
      $ids = array($ids);
    }
    foreach ($ids as $id) {
      $this->pageletIds[] = $id;
    }
    return $this;
  }

  public function getPageletIds() {
    return $this->pageletIds;
  }

  public function setPrintType($type) {
    // 这个方法主要用于测试打印资源表的效果
    // 一般不需要手动调用
    if (isset($this->response)) {
      $this->response->setPrintType($type);
    }
  }

  public function getPrintType() {
    if (isset($this->response)) {
      return $this->response->getPrintType();
    }
  }

  public function getResponseObject() {
    // 提供获取私有reponse的方法, 方便调用设置cdn等功能
    return $this->response;
  }

  /**
   * 渲染期间加载对应的部件. 正常渲染则直接输出部件html内容, 否则记录页面部件
   * @param BriskPageletInterface $pagelet
   * @return BriskSafeHTML|$this
   */
  public function loadPagelet($pagelet) {
    $pagelet->setParentView($this);
    if ($this->mode === RENDER_NORMAL) {
      return $pagelet->renderAsHTML();
    } else {
      $this->pagelets[$pagelet->getId()] = $pagelet;
      return $this;
    }
  }

  public function getPagelets() {
    return $this->pagelets;
  }

  public function renderResourcesOfType($type) {
    return $this->response->renderResourcesOfType($type);
  }

  public function render() {
    $html = '';
    switch ($this->mode) {
      case RENDER_AJAXPIPE:
        $this->emitHeader('Content-Type', 'application/json');
        // 这里不需要加载页面全局的资源, 不再调用`willRender`
        $html = $this->renderAsJSON();
        break;
      case RENDER_NORMAL:
        $this->emitHeader('Content-Type', 'text/html');
        $this->willRender();
        $html = $this->renderAsHTML();
        break;
    }

    $this->emitData($html);
  }

  protected function emitHeader($name, $value) {
    header("{$name}: {$value}", $replace = false);
  }

  protected function emitData($data) {
    echo $data;

    // NOTE: We don't call flush() here because it breaks HTTPS under Apache.
    // See T7620 for discussion. Even without an explicit flush, PHP appears to
    // have reasonable behavior here: the echo will block if internal buffers
    // are full, and data will be sent to the client once enough of it has
    // been buffered.
  }

  protected function renderAsHTML() {
    // 这个方法在正常请求的时候输出页面全部的html, 这个可被复写的方法.
    // 希望以此为接口实现不同的模板渲染, 或者直接由php输出.
    // `getTemplateString`可以使用也可以不使用, 并不强制.
    return (string)hsprintf(
      $this->getTemplateString(),
      BriskDomProxy::escapeHtml($this->title),
      $this->renderResourcesOfType('css'),
      new BriskSafeHTML(''),
      $this->renderResourcesOfType('js')
    );
  }

  protected function renderAsJSON() {
    $res = array('payload' => array());

    // 挑选需要渲染的部件
    foreach ($this->pagelets as $pagelet_id) {
      if (!isset($this->getPagelets()[$pagelet_id])) {
        throw new Exception(pht(
          'No widget with id %s found in %s',
          $pagelet_id,
          __CLASS__
        ));
      }

      $pagelet = id($this->getPagelets())[$pagelet_id];
      $res['payload'][$pagelet_id] = $pagelet->renderAsHTML();
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