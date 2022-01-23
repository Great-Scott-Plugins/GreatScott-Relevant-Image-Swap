<?php
/**
 * Plugin Name: GreatScott Relevant Image Swap
 * Plugin URI: https://greatscottplugins.com/relevantimageswap/
 * Description:
 * Author: GreatScottPlugins
 * Author URI: https://greatscottplugins.com
 * Version: 1.0.0
 * Text Domain: title-case
 * Domain Path: /languages/
 * Min WP Version: 4.9.7
 * Requires PHP: 5.7
 *
 * Copyright (c) 2021 Great Scott Plugins
 *
 * GNU General Public License, Free Software Foundation <https://www.gnu.org/licenses/gpl-3.0.html>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     GreatScottPlugins\GreatScottRelevantImageSwap
 * @author      GreatScottPlugins
 * @copyright   Copyright (C) 2021 GreatScottPlugins. All rights reserved.
 *
 **/

require_once __DIR__ . '/vendor/autoload.php';

use GreatScottPlugins\GreatScottRelevantImageSwap\RelevantImageSwap;

RelevantImageSwap::load(__FILE__);