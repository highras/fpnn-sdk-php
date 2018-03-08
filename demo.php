<?php 

require_once "./vendor/autoload.php";

use Fpnn\TCPClient;

$client = new TCPClient("localhost", 13099);

$peerPubKeyData = file_get_contents('./server-public.key');
$client->enableEncryptor($peerPubKeyData);
//$client->enableEncryptor($peerPubKeyData, 'secp256r1', 256);

while (true) {
	try {
	    $answer = $client->sendQuest("two", array("a"=>1, "b"=>"bbb"));
	    var_dump($answer);
	} catch(\Exception $e) {
	    echo $e->getMessage();
	    echo "\n";
	    echo $e->getCode();
	    echo "\n";
	    print_r($e->exPayload);
	}
	sleep(1);
}