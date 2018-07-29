<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include __DIR__ . '/../vendor/autoload.php';

(new \Via\Server('tcp://0.0.0.0:8082'))->run();
