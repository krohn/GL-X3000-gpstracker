#! /usr/bin/php-cli

<?php

require __DIR__ . "/../.www/gps/GpxClasses.php";
require __DIR__ . "/../.www/gps/LocalConfig.php";

function formatBoundsJson($bounds) {
	return $bounds['min']['lat'] . ', ' . $bounds['min']['lon'] . ' - ' . 
		$bounds['max']['lat'] . ', ' . $bounds['max']['lon'] . ' (' .
		$bounds['width'] . 'x' . $bounds['height'] . ')';
}

function formatBoundsXml($bounds) {
	return formatBoundsJson($bounds->jsonSerialize());
}

function httpResponseCode($header) {
	$matches;
	
	if (preg_match('/^HTTP\/\d+\.\d+\s(\d+)\s.*/', $header[0], $matches))
		return $matches[1];
	
	return false;
}

function parseJson($name) {
	global $localURL;
	global $localStreamContext;
	
	echo "parseJson: " . $name . "\n\n";

	$http_response_header;		// prevent runtime error
	$startTime = microtime(true);

	$json = json_decode(file_get_contents($localURL . '?f=' . $name . '&m=', false, $localStreamContext), true);
	
	if (($responseCode=httpResponseCode($http_response_header)) != 200) {
		echo "got http " . $responseCode. "\n\n";
		return;
	}

	$parseTime = microtime(true) - $startTime;

	if ($json != null && isset($json['gpx'])) {
		$gpx=$json['gpx'];
		echo "gpx: version " . $gpx['version'] . "\n";
		echo "name: " . $gpx['name'] . 
			", time " . convDate($gpx['time']) . "\n";
		echo "\tbounds " . formatBoundsJson($gpx['bounds']) . "\n\n"; 
	//		(($wpts=count($gpx->getWpts())) > 0 ? ", wpts " . $wpts : "") ."\n";

		echo "tracks: " . count($gpx['trks']) . "\n";
		foreach ($gpx['trks'] as $gpxTrk) {
			echo "trk: #" . $gpxTrk['nr'] . " " . $gpxTrk['name'];
	//		if ($gpxTrk->getDesc() != "")
	//			echo " (" . $gpxTrk->getDesc() . ")";
			echo ", time " . $gpxTrk['time'];
	//		echo "\n segs " . count($gpxTrk->getTrkSegs()) . ", duration ", formatDuration($gpxTrk->getDuration());
			echo ", trkpts " . $gpxTrk['trkpts'] . ", length: " . $gpxTrk['distance'] . " km" . 
		"\n\tbounds " . formatBoundsJson($gpxTrk['bounds']) . "\n";
	//		foreach ($gpxTrk->getTrkSegs() as $seg) {
	//			echo "  seg: " . $seg->getGpxBounds()->toString() . "\n";
	//		}
			echo "\n";
		}
	}

	echo sprintf("http:   %3.3f s\n",  $parseTime);
	echo sprintf("xml:    %3.3f s\n",  $json['parse']['xml']);
	echo sprintf("gpx:    %3.3f s\n",  $json['parse']['gpx']);
	echo sprintf("memory: %d\n",  memory_get_usage());

	echo "\n";
}

function parseXml($name) {
	global $localURL;
	global $localStreamContext;

	echo "parseXml: " . $name . "\n\n";
	$parse=array();

	// set ssl
	if (isset($localStreamContext))
		libxml_set_streams_context($localStreamContext);
	
	$http_response_header;		// prevent runtime error
	$startTime = microtime(true);

	$xml = simplexml_load_file($localURL . '?f=' . $name, SimpleXMLElement::class, LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NSCLEAN | LIBXML_NOERROR );

	if (($responseCode=httpResponseCode($http_response_header)) != 200) {
		echo "got http " . $responseCode. "\n\n";
		return;
	}

	$parse['xml']=microtime(true) - $startTime;

	if ($xml == false) {
		echo "loading failed!\n";
		return;
	}
	
	if ($xml->getName() != 'gpx') {
		echo "no gpx found!\n";
		return;
	}
		
	$startTime = microtime(true);

	$gpx = new Gpx($xml);
	
	$parse['gpx']=microtime(true) - $startTime;

	echo "gpx: version " . $gpx->getVersion() . "\n";
	echo "name: " . $gpx->getName() . 
		", time " . convDate($gpx->getTime()) . "\n";
	echo "\tbounds " . $gpx->getGpxBounds()->toString() . "\n\n"; 

	echo "tracks: " . count($gpx->getTrks()) . "\n";
	foreach ($gpx->getTrks() as $gpxTrk) {
		echo "trk: #" . $gpxTrk->getNumber() . " " . $gpxTrk->getName();
//		if ($gpxTrk->getDesc() != "")
//			echo " (" . $gpxTrk->getDesc() . ")";
		echo ", time " . $gpxTrk->getTime();
//		echo "\n segs " . count($gpxTrk->getTrkSegs()) . ", duration ", formatDuration($gpxTrk->getDuration());
		echo ", trkpts " . $gpxTrk->getTrkPts() . ", length: " . $gpxTrk->getLength() . " km" . 
	"\n\tbounds " . formatBoundsXml($gpxTrk->getGpxBounds()) . "\n";
//		foreach ($gpxTrk->getTrkSegs() as $seg) {
//			echo "  seg: " . $seg->getGpxBounds()->toString() . "\n";
//		}
		echo "\n";
	}

	echo sprintf("xml:    %3.3f s\n",  $parse['xml']);
	echo sprintf("gpx:    %3.3f s\n",  $parse['gpx']);
	echo sprintf("memory: %d\n",  memory_get_usage());
	
//	$json=array();
//	$json['gpx']=$gpx;
//	$json['parse']=$parse;
//	echo "json: " . json_encode($json,  JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR) . "\n";

	echo "\n";
}

if ($argc < 2) {
	echo "parameter required\n";
	exit;
}

echo "url: " . $localURL . "\n\n";

$action='-json';
for($idx=1; $idx<$argc; $idx++) {
	switch($argv[$idx]) {
		case '-json':
		case '-xml':
			$action=$argv[$idx];
			continue 2;
			break;
		default:
			switch($action) {
				case '-json':
					parseJson($argv[$idx]);
					break;
				case '-xml':
					parseXml($argv[$idx]);
					break;
				default:
					break 2;
			}
			break;
	}
}

?>
