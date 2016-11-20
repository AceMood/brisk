<?php

/**
 * @class BriskWebPage
 * @file Abstract Class represents a single web page
 * @author AceMood
 * @email zmike86@gmail.com
 */

//---------------

abstract class BriskWebPage implements BriskWebPageInterface {
  // page title in document tag
  protected $title = '';

  // render mode
  protected $mode = null;

  // pc or mobile
  protected $device = DEVICE_MOBILE;

  // all widgets ids in current page
  protected $pageletIds = array();

  // all widgets in current web page
  protected $pagelets = array();

  // corresponding response object
  protected $response = null;

  // default, we use mobile phone mode, which cause a inlining operation
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
    // For testing. Do not call it directly.
    if (isset($this->response)) {
      $this->response->setPrintType($type);
    }
  }

  public function getPrintType() {
    if (isset($this->response)) {
      return $this->response->getPrintType();
    }
    return MAP_ASYNC;
  }

  public function getResponseObject() {
    return $this->response;
  }

  /**
   * Load pagelets during rendering.
   * @param BriskPagelet $pagelet
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

  // Render web page.
  // If current request is a ajaxpipe request (which with Get parameter ajaxify equal 1),
  // we return json mime-type. Otherwise we return a html.
  public function render() {
    $html = '';
    switch ($this->mode) {
      case RENDER_AJAXPIPE:
        $this->emitHeader('Content-Type', 'application/json');
        // Not invoke `willRender`
        $html = $this->renderAsJSON();
        break;
      case RENDER_NORMAL:
        $this->emitHeader('Content-Type', 'text/html');
        // We need to call `willRender` here because full-page
        // resources should be included. But not in the case of
        // ajaxpipe.
        $this->willRender();
        $html = $this->renderAsHTML();
        break;
    }

    $this->emitData($html);
  }

  // set attributes on body
  public function setDomAttributes($attributes) {}

  // get attributes on body
  public function getDomAttributes() {}

  // set the response header once a time
  protected function emitHeader($name, $value) {
    header("{$name}: {$value}", $replace = false);
  }

  // output the response content to client
  protected function emitData($data) {
    echo $data;

    // NOTE: We don't call flush() here because it breaks HTTPS under Apache.
    // See T7620 for discussion. Even without an explicit flush, PHP appears to
    // have reasonable behavior here: the echo will block if internal buffers
    // are full, and data will be sent to the client once enough of it has
    // been buffered.
  }

  protected function renderAsHTML() {
    // Used to render full-page request html, should be override.
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

    // pick up pagelets
    foreach ($this->pageletIds as $pagelet_id) {
      if (!isset($this->getPagelets()[$pagelet_id])) {
        throw new Exception(pht(
          'No widget with id %s found in %s',
          $pagelet_id,
          __CLASS__
        ));
      }

      $pagelet = id($this->getPagelets())[$pagelet_id];
      $res['payload'][$pagelet_id] = $pagelet->renderAsHTML();
      $res['js'] = $this->response->renderAjaxResponseResourcesOfType('js');
      $res['css'] = $this->response->renderAjaxResponseResourcesOfType('css');
      $res['script'] = $this->response->produceAjaxScript();
      $res['style'] = $this->response->produceAjaxStyle();
    }

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

  // Triggered before rendering, overwritten by subclasses.
  // require SR in this method, generally for global SR.
  protected function willRender() {}
}