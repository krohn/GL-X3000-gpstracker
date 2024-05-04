<?php

require 'GpxClasses.php';

$gpxDir = '../../gps';
$metaPrefix = '.meta_';

function fileNotFound() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not found', true, 404);
	exit;
}

function methodNotAllowed() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 405 Method not allowed', true, 405);
	exit;
}

function serveFile($file) {
	global $gpxDir;
	global $metaPrefix;

	$name = $gpxDir . "/" . $file;

	if ($_SERVER['REQUEST_METHOD'] != 'GET')
		methodNotAllowed();

	// file exists ?
	if (! file_exists($name) || substr(basename($name), 0, strlen($metaPrefix)) == $metaPrefix)
		fileNotFound();

	// serve metadata
	if (array_key_exists('m', $_GET)) {
		header('Content-Type: application/json');

		// file exists
		$meta = './' . $gpxDir . "/" . $file;
		$meta = dirname($meta) . '/' . $metaPrefix . basename($meta);
		if (file_exists($meta) && filemtime($meta) > filemtime($name)) {
			readfile($meta);
			exit;
		}

		// generate meta
		$json=array();
		$json['file']['size']=filesize($name);
		$json['file']['mtime']=date('d.m.Y H:i.s T', filemtime($name));

		$startTime=microtime(true);
		$xml = simplexml_load_file('http://localhost/' . preg_replace("/((\?|&))m=/", "", $_SERVER['REQUEST_URI']), 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NSCLEAN | LIBXML_NOERROR);

		$parse=array();
		$parse['xml']=microtime(true) - $startTime;

		if ($xml instanceof SimpleXMLElement && $xml->getName() == 'gpx') {
			$startTime=microtime(true);

			$gpx = new Gpx($xml);

			$parse['gpx']=microtime(true) - $startTime;
			$json['gpx']=$gpx;
		}

		$parse['mem']=memory_get_usage();

		$json['parse']=$parse;

		file_put_contents($meta, json_encode($json, JSON_PARTIAL_OUTPUT_ON_ERROR));

		readfile($meta);
		exit;
	}

	// serve file
	header('Content-Type: ' . (array_key_exists('s', $_GET) ? 'application/octet-stream' : 'text/xml'));
	header('Content-disposition: inline; filename="' . basename($name) . '"');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Last-Modified: ' . date('m/j/Y H:i:s T', filemtime($name)));
	readfile($name);

	// auto close tags
	$output=shell_exec('tail -n 3 ' . escapeshellcmd($name) . ' | grep -E "</gpx>" -c ');
	if ($output != false && $output != null && intval($output) == 0) {
		$retval;
		exec('grep -E "<[/]{0,1}trkseg>" -c ' . $name, $output, $retval);
		$closeSeg=($retval == 0 && ($num=intval($output[0])) != 0 && ($num % 2) != 0);
		exec('grep -E "<[/]{0,1}trk>" -c ' . $name, $output, $retval);
		$closeTrk=($retval == 0 && ($num=intval($output[0])) != 0 && ($num % 2) != 0);
		exec('grep -E "<[/]{0,1}gpx(\s|>)" -c ' . $name, $output, $retval);
		$closeGpx=($retval == 0 && ($num=intval($output[0])) != 0 && ($num % 2) != 0);

		if ($closeSeg or $closeTrk or $closeGpx)
			echo "<!-- auto closed -->\n" . ($closeSeg ? "</trkseg>\n" : "") . ($closeTrk ? "</trk>\n" : "") . ($closeGpx ? "</gpx>\n" : "");
	}
}

function serveHtml() {
	global $gpxDir;
	global $metaPrefix;

	$uri=$_SERVER['REQUEST_URI'];
	switch (($dir=dirname($uri))) {
		case '/gps/css':
		case '/gps/js':
			$name=basename($dir) . '/' . basename($uri);
			if (file_exists($name)) {
				header('Content-Type: text/' . (basename($dir) == 'css' ? 'css' : 'javascript'));
				header('Cache-Control: max-age=86400');
				readfile($name);
				exit;
			} else
				fileNotFound();
			break;
		default:
			if ($uri != '/gps/') {
				header('Location: /gps/');
				exit;
			}
			break;
	}

	header('Content-Type: text/html');

	echo '<!DOCTYPE html><html><title>GPS</title><head>' . 
		'<meta name=viewport content="width=device-width, initial-scale=1.5" />' . 
		'<link rel="stylesheet" href="/gps/css/gps.css">' .
		'<script src="/gps/js/gps.js"></script>' .
		'</head><body onclick="javascript:oc()">' .
		'<div id="overlay"></div>';
	
	exec('find ./' . $gpxDir . ' -name "[^.]*.gpx"', $output, $ret);
	sort($output);
    $lastDirE;
	echo '<ul class="fL">';
	foreach ($output as $gpx) {
		$dir=str_replace('.', '', dirname(substr($gpx, strlen('./' . $gpxDir) + 1)));
		$dirE=explode('/', $dir, 3);

		$closeNode=(isset($lastDirE) ? count($lastDirE) : 0);
		if ( count($dirE) > 0 && $dirE[0] != "" ) {
			for ($i = 0; $i < count($dirE); $i++) {
				if ($closeNode > 0) {
					if ( $dirE[$i] == $lastDirE[$i] )
						$closeNode -= 1;
					else
						break;
				}
			}
			for ($i = 0; $i < $closeNode; $i++)
				echo '</ul>';
			if (isset($lastDirE) && $closeNode == count($lastDirE))
				$closeNode=0;
			for ($i = $closeNode; $i < count($dirE); $i++) {
				$name="";
				for ($j=0; $j <= $i; $j++)
					$name=($name == '' ? '' : $name . '/') . $dirE[$j];
				echo '<li class="fLDL">' . $dirE[$i] . '</li><ul class="fLD" id="ul' . $name . '">';
			}
		} else if (isset($lastDirE) && count($lastDirE) > 0 && $lastDirE[0] != "") {
			for ($i = 0; $i < count($lastDirE); $i++)
				echo "</ul>";
		}
		echo '<li>' . basename($gpx) . "</li>";
		$lastDirE=$dirE;
	}
	echo '</ul><br>';

	echo "</body></html>\n";

	exit;
}

if (! array_key_exists('f', $_GET))
	serveHtml();

serveFile(urldecode($_GET['f']));
