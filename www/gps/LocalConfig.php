<?php

$localURL="localhost/gps/";

//
$allowSelfSignedServerCerts=false;

// client cert settings
// $localClientCert=__DIR__ . '/../certs/<name>.pem';
// $localClientCertPhrase='';

$localStreamContextOptions=array(
	'http' => array(
		'ignore_errors' => false,
	),
);

// disable certificate verification
if (isset($localClientCert) || $allowSelfSignedServerCerts) {
	$localStreamContextOptions['ssl'] = array(
       		"verify_peer" => true,			//  
       		"verify_peer_name" => false,
	);

	if ($allowSelfSignedServerCerts)
		$localStreamContextOptions['ssl']['allow_self_signed']=true;
	if (isset($localClientCert))
		$localStreamContextOptions['ssl']['local_cert']=$localClientCert;
	if (isset($localClientCertPhrase))
		$localStreamContextOptions['ssl']['passphrase']=$localClientCertPhrase;

}

$localStreamContext=stream_context_create($localStreamContextOptions);

$localURL=sprintf('http%s://%s', (isset($localStreamContextOptions['ssl']) ? 's' : ''), $localURL);

unset($localStreamContextOptions);
