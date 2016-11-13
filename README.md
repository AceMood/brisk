## What is brisk
**Brisk Library** is a PHP static resource load framework, inspired by Phabricator/Celerity and 
modified it for fitting our project. 

## Dependency

**Brisk Library** depends on [Libphutil](https://github.com/phacility/libphutil/), which provides
native utility functions. We've already included it in the lib folder.

## Usage

``` php
<?php
include_once('path/to/brisk/__init__.php');
```

Then you can use any Classes Brisk provided. The most important for view-rendering is 
**BriskPageView** and **BriskWidgetView**, you can visit [Demo](https://github.com/Saber-Team/demo-todomvc-react-soi) 
for more information.

## Test

