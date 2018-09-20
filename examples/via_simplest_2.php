<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include 'loader.php';

(new \Via\Server('tcp://0.0.0.0:8082'))->run();
