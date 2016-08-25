<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 16/8/22
 * Time: 下午5:19
 */

$html = 
    <<<EOTEMPLATE
        <!DOCTYPE html>
        <html>
          <head>
            <meta charset="UTF-8" />
            <title>%s</title>
            %s
          </head>
          %s
        </html>
EOTEMPLATE;

echo $html;