<?php
/**
 * Entry point for rendering.
 *
 * @copyright 2014-2015 Roman Parpalak
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @package   S2 Latex Service
 * @link      http://tex.s2cms.ru
 */

require '../vendor/autoload.php';
require '../config.php';

$isDebug = defined('DEBUG') && DEBUG;
error_reporting($isDebug ? E_ALL : -1);

// Setting up external commands
define('LATEX_COMMAND', TEX_PATH.'latex -output-directory='.TMP_DIR);
define('DVISVG_COMMAND', TEX_PATH.'dvisvgm %1$s -o %1$s.svg -n -e -v0 --scale='.(1.00375 * OUTER_SCALE));
define('DVIPNG_COMMAND', TEX_PATH.'dvipng -T tight %1$s -o %1$s.png -D '.(96 * OUTER_SCALE));
define('SVG2PNG_COMMAND', 'rsvg-convert %1$s.svg -d 96 -p 96 -b white'); // stdout

define('SVGO', realpath(SVGO_PATH).'/svgo -i %s');
define('GZIP', 'gzip -cn6 %1$s > %1$s.gz');
define('OPTIPNG', 'optipng %1$s');
define('PNGOUT', 'pngout %1$s');

function error400 ($error = 'Invalid formula')
{
	header($_SERVER['SERVER_PROTOCOL'].' 400 Bad Request');
	include '400.php';
}


//ignore_user_abort();
ini_set('max_execution_time', 10);
header('X-Powered-By: S2 Latex Service');

$templater = new Templater(TPL_DIR);

$renderer = new Renderer($templater, TMP_DIR, LATEX_COMMAND, DVISVG_COMMAND);
$renderer->setSVG2PNGCommand(SVG2PNG_COMMAND);
if (defined('LOG_DIR'))
	$renderer->setLogDir(LOG_DIR);
$renderer->setDebug($isDebug);

$processor = new Processor($renderer, CACHE_SUCCESS_DIR, CACHE_FAIL_DIR);
$processor
	->addSVGCommand(SVGO)
	->addSVGCommand(GZIP)
	->addPNGCommand(OPTIPNG)
	->addPNGCommand(PNGOUT)
;

try
{
	$processor->parseURI(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}
catch (Exception $e)
{
	error400($isDebug ? $e->getMessage() : 'Invalid formula');
	die;
}

if ($processor->prepareContent())
	$processor->echoContent();
else
	error400($isDebug ? $processor->getError() : 'Invalid formula');

if (!$isDebug)
	$processor->saveContent();
