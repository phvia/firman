<?php
/**
 * Via package.
 *
 * @license MIT
 * @author farwish <farwish@foxmail.com>
 */

include 'loader.php';

(new \Via\Server('tcp://0.0.0.0:8083'))->run();
(new \Via\Server('tcp://0.0.0.0:8084'))->run();
(new \Via\Server('tcp://0.0.0.0:8085'))->run();
