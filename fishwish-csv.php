<?php

/*
Plugin Name: Fishwish CSV
Description: Generuoja csv faila
Version: 1.0.1
Author: ITErdve
Text Domain: kainoslt
*/

namespace fishwish;

if (!defined('ABSPATH')) {
	die();
}


require_once ('csvgenerator.php');

use fishwish\csvgenerator;

new csvgenerator();
