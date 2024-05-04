<?php

$gpxDir = '../../upload_gps';
$metaPrefix = '.meta_';

function fileNotFound() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not found', true, 404);
	exit;
}

function methodNotAllowed() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 405 Method not allowed', true, 405);
	exit;
}

function badRequest() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
	exit;
}

function notAcceptable($extra = null) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 406 Not Acceptable', true, 406);
	if (isset($extra))
		echo $extra;
	exit;
}

function internalServerError() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	exit;
}

function processGet() {
	global $gpxDir;
	global $metaPrefix;

	// search given filename
	if (array_key_exists('f', $_GET)) {
		$name=$gpxDir . '/' . $_GET['f'];
		if (! file_exists($name))
			fileNotFound();

		if (array_key_exists('r', $_GET)) {
			$rename=$gpxDir . '/' . $_GET['r'];
			if (!is_dir(dirname($rename)))
				mkdir(dirname($rename), 0770, true);
			if (!rename($name, $rename))
				notAcceptable();

			header('Content-Type: application/json');
			echo json_encode(getFileInfos($rename));
			exit;
		}

		if (array_key_exists('m', $_GET)) {
			header('Content-Type: application/json');
			echo json_encode(getFileInfos($name));
			exit;
		}

		header('Content-Type: text/xml');

		$amount=(isset($_GET['a']) ? $_GET['a'] : filesize($name));
		$offset=(isset($_GET['o']) ? $_GET['o'] : 0);

		$file=fopen($name, 'r');
		fseek($file, $offset, SEEK_SET); 
		echo fread($file, $amount);
		fclose($file);

		exit;
	}

	// list directory
	header('Content-Type: application/json');

	exec('find ./' . $gpxDir . ' -name "[^.]*.gpx"', $output, $ret);
	sort($output);
	$json=array();
	foreach ($output as $gpx) {
		$dir=str_replace('.', '', dirname(substr($gpx, strlen('./' . $gpxDir) + 1)));

		$oDir=&$json;
		foreach (explode('/', $dir, 3) as $dir) {
			if ($dir == "")
				continue;
			if (! isset($oDir[$dir]))
				$oDir[$dir]=array();
			$oDir=&$oDir[$dir];
		}

		$oDir['files'][]=getFileInfos($gpx);
	}

	echo json_encode($json);

	exit;
}

function getFileInfos($gpx) {
	clearstatcache(true, $gpx);
	$file=array();
	$file['name']=basename($gpx);
	$file['mtime']=date('d.m.Y H:i.s T', filemtime($gpx));
	$file['size']=filesize($gpx);
	$file['md5']=md5_file($gpx);

	return $file;
}

function processPut() {
	global $gpxDir;
	global $metaPrefix;

	if (! isset($_GET['f']))
		badRequest();

	$name=$_GET['f'];
	if (substr($name, 0, strlen($metaPrefix)) == $metaPrefix)
		badRequest();
	$offset=(isset($_GET['o']) ? $_GET['o'] : 0);	
	$amount=(isset($_GET['a']) ? $_GET['a'] : 0);

	$fName='./' . $gpxDir . '/' . $name;
	if ($offset>0 && filesize($fName) != $offset)
		notAcceptable(json_encode(getFileInfos($fName)));

	// create directory
	if (!is_dir(dirname($fName))) {
		exec("mkdir -p " . dirname($fName));
	}
	// open files
	if (($file=fopen($fName, 'c')) != false && ($put=fopen('php://input', 'r')) != false) {
		fseek($file, $offset, SEEK_SET);
		$chunk=1024;
		do {
			$data=fread($put, $chunk);
			$wrote=fwrite($file, urldecode($data));
		} while (strlen($data) == $chunk);

		if (filesize($fName) > ftell($file))
			ftruncate($file, 0);
		fclose($file);
		fclose($put);
	} else
		internalServerError();

	echo json_encode(getFileInfos($fName));
}

switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		processGet();
		break;
	case 'PUT':
		processPut();
		break;
	default:
		methodNotAllowed();
		break;
}

?>
