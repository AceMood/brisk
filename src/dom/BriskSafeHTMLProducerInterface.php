<?php

/**
 * @file Implement this interface to mark an object as capable of producing a
 * BriskSafeHTML representation. This is primarily useful for building
 * renderable HTML views.
 */

interface BriskSafeHTMLProducerInterface {

  public function produceBriskSafeHTML();

}
