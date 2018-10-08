<?php
/**
 * Firman package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include 'loader.php';

(new \Firman\Server('tcp://0.0.0.0:8082'))->run();
