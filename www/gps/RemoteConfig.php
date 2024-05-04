<?php

$remoteURL="";
$remoteListRefreshInterval=21600;

//
$allowSelfSignedServerCerts=true;
$remoteVerifyPeer=false;
$remoteVerifyPeerName=false;
// CA sert
// $remoteCAInfo=__DIR__ . '/../certs/ca.pem';
// client cert settings
// $remoteClientCert=__DIR__ . '/../certs/<name>.pem';
// $remoteClientCertPhrase='';

// disable certificate verification
$remoteStreamContextOptions=array(
	'http' => array(
		'ignore_errors' => false,
	),
);

if (isset($remoteClientCert) || $allowSelfSignedServerCerts) {
	$remoteStreamContextOptions['ssl'] = array(
       		"verify_peer" => $remoteVerifyPeer,
       		"verify_peer_name" => $remoteVerifyPeerName,
	);

	if (isset($remoteCAInfo))
		$remoteStreamContextOptions['ssl']['cafile']=$remoteCAInfo;
	if (isset($allowSelfSignedServerCerts))
		$remoteStreamContextOptions['ssl']['allow_self_signed']=$allowSelfSignedServerCerts;
	if (isset($remoteClientCert))
		$remoteStreamContextOptions['ssl']['local_cert']=$remoteClientCert;
	if (isset($remoteClientCertPhrase))
		$remoteStreamContextOptions['ssl']['passphrase']=$remoteClientCertPhrase;

}

$remoteStreamContext=stream_context_create($remoteStreamContextOptions);

$remoteURL=sprintf('http%s://%s', (isset($remoteStreamContextOptions['ssl']) ? 's' : ''), $remoteURL);

unset($remoteStreamContextOptions);

?>
