<?php

use OCA\NextcloudAppTemplate\AppInfo\Application;
use OCP\Util;

/* @var array $_ */
$script = $_['script'];
$style = $_['style'];
Util::addScript(Application::APP_ID, Application::JS_DIR . "/$script");
Util::addStyle(Application::APP_ID, Application::CSS_DIR . "/$style");
?>
<div id="nextcloudapptemplate-app"></div>
