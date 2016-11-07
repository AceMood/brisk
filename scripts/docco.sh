#!/usr/bin/env bash

docco src/__init__.php src/api.php src/const.php
docco src/**/**.php -o docs/