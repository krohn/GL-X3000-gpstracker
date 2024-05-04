#! /usr/bin/php-cli

<?php

require __DIR__ . "/../www/gps/RemoteConfig.php";

$gpxDir="/opt/gpstracker/gps";
$remoteListingJson=$gpxDir . '/' . '.remote-listing.json';

class ProbeFile {
	public const DIR_NOT_FOUND = 'dir_not_found';
	public const FILE_NOT_FOUND = 'file_not_found';
	public const FILE_EXISTS_MD5_MISMATCH = 'file_exists_md5_mismatch';
	public const FILE_EXISTS_SIZE_MISMATCH = 'file_exists_size_mismatch';
	public const FILE_RENAMED = 'file_renamed';
	public const FILE_EXISTS = 'file_exists';
}
	
function writeLog($log) {
	if ($log == "")
		return;
	
	echo $log . "\n";
	exec('logger -t "gpx-upload" "' . $log . '"');
}

function httpResponseCode($header) {
	$matches;
	
	if (!isset($header))
		return false;
	
	if (preg_match('/^HTTP\/\d+\.\d+\s(\d+)\s.*/', $header[0], $matches))
		return $matches[1];
	
	return false;
}

function checkRemote() {
	global $remoteURL;
	global $remoteStreamContext;

	echo "checking remote connection: ";
	
	$http_response_header;	// prevent runtime error
	$content=file_get_contents($remoteURL, false, $remoteStreamContext);
	
	echo sprintf("%s\n\turl: %s\n\t", ($content != false ? 'ok' : 'failed'), $remoteURL) .
		($content != false ? sprintf('%d bytes received ', strlen($content)) : '') .
		(($lastError=error_get_last()) != null ? sprintf('error %d (%s) ', $lastError['type'], $lastError['message']) : '') .
		(($responseCode=httpResponseCode($http_response_header)) != false ? sprintf('(HTTP %d) ', $responseCode) : '') . 
		"\n\n";
}

function getFileContent($name, $offset, $amount) {
	global $gpxDir;

	$file=fopen($gpxDir . '/' . $name, 'r');
	if ($file == false)
		return false;

	if ($offset > 0)
		fseek($file, $offset, SEEK_SET);
	$lContent=fread($file, ($amount > 0 ? $amount : filesize($gpxDir . '/' . $name)));
	fclose($file);
	
	return $lContent;
}

function probeFile($name, &$file) {
	global $gpxDir;
	global $json;

	$dirs=explode('/', dirname($name), 3);
	
	$rDir=&$json;
	$probe;
	foreach ($dirs as $dir) {
		if ($dir == ".")
			break;
		if (!isset($rDir[$dir])) {
			$probe=ProbeFile::DIR_NOT_FOUND;
			break;
		}
		
		$rDir=&$rDir[$dir];
	}
	
	if (!isset($probe) && isset($rDir['files']))
		foreach ($rDir['files'] as &$rFile) {
			if ($rFile['name'] == basename($name)) {
				$file=$rFile;
				break;
			}
		}
		
	// maybe local file renamed
	if (isset($probe) || ! isset($file)) {
		$rSize=filesize($gpxDir . '/' . $name);
		$rMD5=md5_file($gpxDir . '/' . $name);

		$dirs=array('/' => $json);
		$found=true;
		while ($found || count($dirs) >= 10) {
			$found=false;
			
			foreach($dirs as $dKey => $dData) {
				if ($dKey == 'files')
					continue;

				foreach ($dData as $sKey => $sData) {
					if ($sKey == 'files')
						continue;
					
					if (!array_key_exists($dKey . $sKey . '/', $dirs)) {
						$dirs[$dKey . $sKey . '/']=$sData;
						$found=true;
					}
				}
			}
		}

		foreach ($dirs as $remKey => $remDir) {
			if (isset($remDir['files'])) {
				foreach ($remDir['files'] as $remFile)
					if ($remFile['size'] == $rSize && $remFile['md5'] == $rMD5) {
						$file=$remFile;
						// inject path for rename
						$file['path']=substr($remKey, 1);
						return ProbeFile::FILE_RENAMED;
					}
			}
		}
	}
	
	if (isset($probe))
		return $probe;
	
	if (! isset($file))
		return ProbeFile::FILE_NOT_FOUND;
	
	// probe size
	if (filesize($gpxDir . '/' . $name) != $file['size'])
		return ProbeFile::FILE_EXISTS_SIZE_MISMATCH;
		
	// probe md5
	if (md5_file($gpxDir . '/' . $name) != $file['md5'])
		return ProbeFile::FILE_EXISTS_MD5_MISMATCH;
		
	return ProbeFile::FILE_EXISTS;
}

function transferFile($probe, $name, &$file) {
	global $gpxDir;
	global $json;

	// search dir
	$rDir=&$json;
	$dirs=explode('/', dirname($name), 3);
	foreach ($dirs as $dir) {
		if ($dir == ".")
			break;
		if (! isset($rDir[$dir]))
			$rDir[$dir]=array();
		$rDir=&$rDir[$dir];
	}

	if (!isset($rDir['files']))
		$rDir['files']=array();
	$fileKey;
	foreach($rDir['files'] as $kFile => $eFile) {
		$fileKey=$kFile;
		if ($eFile['name'] == basename($name)) {
			break;
		}
	}
	
	$log=sprintf("%-50s: upload", $name);
	
	// offset && amount
	$o=$a=0;
	$upload;
	$httpCode;
	$curlError;
	switch($probe) {
		case ProbeFile::DIR_NOT_FOUND:
		case ProbeFile::FILE_NOT_FOUND:
			if (($upload=uploadFile($name, $curlError, $httpCode)) != false)
				$rDir['files'][]=$upload;
			break;
		case ProbeFile::FILE_EXISTS_SIZE_MISMATCH:
			// upload delta ?
			if (compareFile($name)) {
				$o=$file['size'];
				$a=filesize($gpxDir . '/' . $name) - $o;
				$log.=sprintf(" %d@%d", $a, $o);
			}
		case ProbeFile::FILE_EXISTS_MD5_MISMATCH:
			if (($upload=uploadFile($name, $curlError, $httpCode, $o, $a)) != false)
				$rDir['files'][$fileKey]=$upload;
			break;
	}

	$log.=sprintf(" %s", 
//		($upload != false ? "ok" : "failed (" . 
		($upload != false ? "ok" : "(" . 
			($curlError != 0 ? "CURL " . $curlError : "HTTP " . $httpCode) . ")")
		);
	writeLog($log);
}

function compareFile($name, $offset=0, $amount=500) : bool {
	global $remoteURL;
	global $remoteStreamContext;
	global $gpxDir;

	$rContent=file_get_contents($remoteURL . '?f=' . $name . '&o=' . $offset . '&a=' . $amount, false, $remoteStreamContext);
	if ($rContent == false)
		return false;
	
	if (($lContent=getFileContent($name, $offset, $amount)) == false)
		return false;

	return ($rContent == $lContent);
}

function uploadFile($name, &$curlError, &$httpCode, $offset=0, $amount=0) {
	global $remoteURL;
	global $remoteCAInfo;
	global $remoteVerifyPeer;
	global $remoteVerifyPeerName;
	global $remoteClientCert;
	global $remoteClientCertPhrase;
	global $remoteURL;
	global $gpxDir;

	$fileName=$gpxDir . '/' . $name;
	$uploadFileHandle=fopen($fileName, 'r');
	
	if ($uploadFileHandle == false) {
		return;
	}

	$fileSize=filesize($fileName);
	if ($offset > 0)
		fseek($uploadFileHandle, $offset, SEEK_SET);
	
	$url=$remoteURL . '?f=' . $name .
		($offset > 0 ? '&o=' . $offset : '') .
		($amount > 0 ? '&a=' . $amount : '');
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_PUT, true);
	curl_setopt($ch, CURLOPT_HTTP_CONTENT_DECODING, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_READFUNCTION, 'uploadFileCurlCB');
	curl_setopt($ch, CURLOPT_INFILE, $uploadFileHandle);
	curl_setopt($ch, CURLOPT_INFILESIZE, ($amount > 0 ? $amount : $fileSize));
	if (isset($remoteCAInfo))
		curl_setopt($ch, CURLOPT_CAINFO, $remoteCAInfo);
	if (isset($remoteVerifyPeer))
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $remoteVerifyPeer);
	if (isset($remoteVerifyPeerName))
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $remoteVerifyPeerName);
	if (isset($remoteClientCert))
		curl_setopt($ch, CURLOPT_SSLCERT, $remoteClientCert);
	if (isset($remoteClientCertPhrase))
		curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $remoteClientCertPhrase);

	$response = curl_exec($ch);
	$curlError=curl_errno($ch);
	
	fclose($uploadFileHandle);
	curl_close($ch);

	if ($response === false && $curlError != 0)
		return false;
	
	switch($httpCode=curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
		case 200:	// request ok
		case 406:	// offset mismatch (new meta data in response)
			break;
		default:
			return false;
			break;
	}
			
	return json_decode($response, TRUE);
}

function uploadFileCurlCB($ch, $fh, $length = false) {
	if (!is_resource($fh)) {
		return 0;
	}
	
	if (!$length)
		$length=512*1024;

	return fread($fh, $length);
}

function renameFile($name, &$file, &$httpCode) {
	global $remoteURL;
	global $remoteStreamContext;
	global $gpxDir;
	global $json;

	$remPath=$file['path'];
	unset($file['path']);
	$remName=$remPath . $file['name'];
	
	// rename file on remote
	$http_response_header;	// prevent runtime error
	$rContent=file_get_contents($remoteURL . '?f=' . $remName . '&r=' . $name, false, $remoteStreamContext);
	if ($rContent == false) {
		return false;
	}

	switch(($httpCode=httpResponseCode($http_response_header))) {
		case 200:
			break;
		default:
			return false;
			break;
	}

	// update remote listing
	$result=false;
	// add new entry
	$rDir=&$json;
	foreach(explode('/', $name, 3) as $dir) {
		if ($dir == basename($name))
			break;
		if (!isset($rDir[$dir]))
			$rDir[$dir]=array();
		$rDir=&$rDir[$dir];
	}
	if (!isset($rDir['files']))
		$rDir['files']=array();

	$file['name']=basename($name);
	$rDir['files'][]=$file;
	
	// remove old entry
	$rDir=&$json;
	foreach(explode('/', $remName, 3) as $dir) {
		if ($dir == basename($remName))
			break;
		if (!isset($rDir[$dir]))
			break;
		$rDir=&$rDir[$dir];
	}
	if (isset($rDir['files'])) {
		foreach($rDir['files'] as $idx => $val)
			if ($val['name'] == basename($remName)) {
				unset($rDir['files'][$idx]);
				$result=true;
			}
	}

	return $result;
}

function loadRemoteListing($name, $url, $reloadEnabled=true) {
	global $remoteStreamContext;
	global $remoteListRefreshInterval;
	
	$log="";
	// reload remote listing
	if ( $reloadEnabled == true && (! file_exists($name) || filemtime($name) < (time() - ($remoteListRefreshInterval - 10)))) {
		$log="reloading listing ... ";
		$http_response_header;	// prevent runtime error
		$remoteListing=file_get_contents($url, false, $remoteStreamContext);
		
		switch(($responseCode=httpResponseCode($http_response_header))) {
			case 200:
				$file = fopen($name, 'w');
				fwrite($file, $remoteListing);
				fclose($file);
				touch($name);
				clearstatcache(true, $name);
				$log.="ok ";
				break;
			default:
				$log.="failed ";
				if (is_numeric($responseCode))
					$log.=sprintf('(HTTP %d) ', $responseCode);
				break;
		}
	}
	
	$json=json_decode(file_get_contents($name), TRUE);
	
	// don't write to log
	if ($reloadEnabled == false)
		return $json;
	
	writeLog(sprintf("%s%s", $log, (isset($json) ? "" : "listing is empty")));
	
	if (!isset($json)) {
		$json=array('files' => array());
	}

	return $json;
}

function saveRemoteListing() {
	global $json;
	global $remoteListingJson;
	
	if (file_exists($remoteListingJson))
		$lastJson=file_get_contents($remoteListingJson);
	$newJson = json_encode($json);
	if (!isset($lastJson) || $lastJson != $newJson) {
		$mtime=(file_exists($remoteListingJson) ? filemtime($remoteListingJson) : null);
		echo "\njson " .
			(file_put_contents($remoteListingJson, $newJson) ? "changes written" : "write failed") .
			"\n";
		touch($remoteListingJson, $mtime);
		clearstatcache(true, $remoteListingJson);
	}
}

function testLocalFilesRenamed(&$renamedFiles=array(), $path='', $jsonDirty=null) {
	global $json;
	global $gpxDir;
	global $remoteListingJson;
	
	if ($jsonDirty == null)
		if (($jsonDirty=loadRemoteListing($remoteListingJson, null, false)) == false)
			return false;

	if (isset($jsonDirty['files']))
		foreach ($jsonDirty['files'] as $key => $file) {
			$fileName=$path . $file['name'];

			$renamedFiles[$fileName]=array(
				  'size'	=> $file['size']
				, 'mtime'	=> $file['mtime']
				, 'md5'		=> $file['md5']
				);
		}
		
	foreach ($jsonDirty as $key => $dir) {
		if ($key == 'files')
			continue;
		// recursiv call
		testLocalFilesRenamed($renamedFiles, $path . $key . '/', $jsonDirty[$key]);
	}
	
	// process lists
	if ($path == '') {
		$output=shell_exec('find ' . $gpxDir . ' -name "[^.]*.gpx" -type f');
		$localFiles=explode("\n", $output);
		
		foreach ($localFiles as $lk => $lf) {
			if ($lf == '') {
				unset($localFiles[$lk]);
				continue;
			}
			
			$fileName=str_replace($gpxDir . '/', '', $lf);

			// update local file data
			$localFiles[$fileName]=array(
				  'size' 	=> filesize($lf)
				, 'mtime' 	=> filemtime($lf)
				, 'md5'		=> md5_file($lf)
				);
			unset($localFiles[$lk]);
			
			if (isset($renamedFiles[$fileName])) {
				// file changed ?
				if ($renamedFiles[$fileName]['size'] == $localFiles[$fileName]['size'] && 
					$renamedFiles[$fileName]['md5'] == $localFiles[$fileName]['md5']
				) // no changea
					unset($renamedFiles[$fileName]);
				unset($localFiles[$fileName]);
			}
		}

		// nothing to do
		if (count($renamedFiles) == 0 || count($localFiles) == 0)
			return;

		// make it public
		$json=$jsonDirty;
		
		foreach ($renamedFiles as $rk => $ro) {
			foreach ($localFiles as $lk => $lo) {
				if ($ro['size'] <= $lo['size']) {
					$lf=fopen($gpxDir . '/' . $lk, 'r');
					
					// calculate md5 for last known size
					$hashCtx=hash_init('md5');
					hash_update_stream($hashCtx, $lf, $ro['size']);
					$lhash=hash_final($hashCtx);
					fclose($lf);
					
					if ($lhash == $ro['md5']) {
						$tempFile=array(
							  'name'	=> basename($rk)
							, 'size' 	=> $renamedFiles[$rk]['size']
							, 'mtime' 	=> $renamedFiles[$rk]['mtime']
							, 'md5' 	=> $renamedFiles[$rk]['md5']
							, 'path' 	=> (basename($rk) != $rk ? dirname($rk) : '')
							);
						// rename on remote
						$renamed=renameFile($lk, $tempFile, $httpCode);
						$extra=($renamed == false && $httpCode != false && $httpCode != 200 ? ' (HTTP ' . $httpCode . ')' : '');
						writeLog(sprintf("%-50s: rename" . ($renamed ? 'd (hash match for last known size)' : ' failed' . $extra), $rk));

						unset($localFiles[$lk]);
						break;
					}
				}
			}
		}

		saveRemoteListing();
	}
}

// echo "remote url: " . $remoteURL . "\n\n";
	if ($argc > 1) {
		switch($argv[1]) {
			case '-check-remote':
				checkRemote();
				exit;
				break;
			case '-usage':
				echo "\nusage: " . basename($argv[0]) . "\n\t-check-remote\n\n";
				exit;
				break;
		}
	}

// check for renamed local files
	testLocalFilesRenamed();
	
// load remote listing
	$json = loadRemoteListing($remoteListingJson, $remoteURL);

// process local files	
	exec('find ' . $gpxDir . ' -name "[^.]*.gpx" -type f', $list, $ret);
	sort($list);
	
	foreach ($list as $name) {
		$fName=str_replace($gpxDir . '/', '', $name);
		$jsonFile=null;
		
		switch(($probe=probeFile($fName, $jsonFile))) {
			case ProbeFile::DIR_NOT_FOUND:
			case ProbeFile::FILE_NOT_FOUND:
			case ProbeFile::FILE_EXISTS_SIZE_MISMATCH:
			case ProbeFile::FILE_EXISTS_MD5_MISMATCH:
				transferFile($probe, $fName, $jsonFile);
				break;
			case ProbeFile::FILE_RENAMED:
				$renamed=renameFile($fName, $jsonFile, $httpCode);
				$extra=($renamed == false && $httpCode != false && $httpCode != 200 ? ' (HTTP ' . $httpCode . ')' : '');
				writeLog(sprintf("%-50s: rename" . ($renamed ? 'd' : ' failed' . $extra), $fName));
				break;
			case ProbeFile::FILE_EXISTS:
				printf("%-50s: ok\n", $fName);
				break;
		}
	}

// write back modified remote listing
	saveRemoteListing();

	echo "\n";

?>