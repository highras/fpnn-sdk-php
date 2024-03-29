<?php

namespace highras\fpnn;

use Elliptic\EC;

define("FPNN_SDK_VERSION", "1.0.5");

define('FPNN_SOCKET_READ_RETRY', 10);

// flag constants
define('FPNN_PHP_FLAG_MSGPACK', 0x80);
define('FPNN_PHP_FLAG_JSON', 0x40);
define('FPNN_PHP_FLAG_ZIP', 0x20);
define('FPNN_PHP_FLAG_ENCRYPT', 0x10);

// package types
define('FPNN_PHP_PACK_MSGPACK', 0);
define('FPNN_PHP_PACK_JSON', 1);

// message types
define('FPNN_PHP_MT_ONEWAY', 0);
define('FPNN_PHP_MT_TWOWAY', 1);
define('FPNN_PHP_MT_ANSWER', 2);

define('FPNN_PHP_VERSION', 1);

define('FPNN_PHP_SEQNUM_ERROR', 201);
define('FPNN_PHP_STATUS_ERROR', 202);
define('FPNN_PHP_JSON_ENCODE_ERROR', 203);
define('FPNN_PHP_JSON_DECODE_ERROR', 204);
define('FPNN_PHP_TIMEOUT_ERROR', 205);
define('FPNN_PHP_MSGPACK_UNPACK_ERROR', 206);
define('FPNN_PHP_ENCRYPTOR_ERROR', 207);

class TCPClient
{
    private $socket;
    private $ip;
    private $port;
    private $timeout;
    private $autoReconnect;

    private $isEncryptor;
    private $canEncryptor;
    private $key;
    private $iv;
    private $strength;

    private $retryTimes = 0;

    function __construct($ip, $port, $timeout = 5000, $autoReconnect = true) // timeout in milliseconds
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->socket = null;
        $this->isEncryptor = false;
        $this->canEncryptor = true;
        $this->autoReconnect = $autoReconnect;
        
        $hasMsgpack = function_exists("msgpack_pack") && function_exists("msgpack_unpack");       // test msgpack api available
        if ($hasMsgpack) {
            $version = phpversion("msgpack");
            if ($version == false || version_compare($version, "0.5.7", "<"))
                throw new \Exception("requires php msgpack version >= 0.5.7", FPNN_PHP_MSGPACK_UNPACK_ERROR);
        } else
            throw new \Exception("requires php msgpack extension", FPNN_PHP_MSGPACK_UNPACK_ERROR);
    }

    function __destruct()
    {
        if (!is_null($this->socket) && !is_bool($this->socket))
            fclose($this->socket);
    }

    private function encrypt($buf, $isEncrypt)
    {
        $strength = 'AES-128-CFB';
        if ($this->strength == 256)
            $strength = 'AES-256-CFB';
        $return = '';
        if ($isEncrypt) {
            $return = openssl_encrypt($buf, $strength, $this->key, OPENSSL_RAW_DATA, $this->iv);
        } else {
            $return = openssl_decrypt($buf, $strength, $this->key, OPENSSL_RAW_DATA, $this->iv);
        }
        return $return;
    }

    public function enableEncryptor($peerPubData, $curveName = 'secp256k1', $strength = 128)
    {
        if ($this->canEncryptor == false) {
            throw new \Exception("enableEncryptor can only be called once, and must before any sendQuest", FPNN_PHP_ENCRYPTOR_ERROR);
        }
        $curveName = in_array($curveName, array('secp256k1')) ? $curveName : 'secp256k1';
        $this->strength = ($strength == 128) ? $strength : 256;

        $ec = new EC($curveName);
        $keyPair = $ec->genKeyPair();

        $peerPubKeyPair = $ec->keyFromPublic('04' . bin2hex($peerPubData), 'hex');

        $secret = hex2bin($keyPair->derive($peerPubKeyPair->getPublic())->toString(16));

        $this->iv = hex2bin(md5($secret));
        if ($this->strength == 128)
            $this->key = substr($secret, 0, 16);
        else {
            if (strlen($secret) == 32)
                $this->key = $secret;
            else
                $this->key = hash('sha256', $secret, true);
        }
        $pubKey = $keyPair->getPublic();
        $sendPubKeyData = hex2bin($pubKey->getX()->toString(16)) . hex2bin($pubKey->getY()->toString(16));

        $this->isEncryptor = true;
        $this->canEncryptor = false;

        try {
            $answer = $this->sendQuest("*key", array("publicKey" => $sendPubKeyData, "streamMode" => false, "bits" => $this->strength));
        } catch (\Exception $e) {
            throw new \Exception("enableEncryptor error: " . $e->getMessage(), FPNN_PHP_ENCRYPTOR_ERROR);
        }
    }

    public function reconnect() {
        $this->reconnectServer();
    }

    private function reconnectServer()
    {
        if (!is_null($this->socket) && !is_bool($this->socket))
            fclose($this->socket);

        $this->socket = @stream_socket_client("tcp://{$this->ip}:{$this->port}", $errno, $errstr, $this->timeout / 1000); 
        if (!$this->socket) {
            throw new \Exception("connect error");
        }
         stream_set_timeout($this->socket, $this->timeout / 1000);
    }

    private function readBytes($len)
    {
        $buf = "";
        $i = 0;
        while (strlen($buf) < $len) {
            $read = @fread($this->socket, $len - strlen($buf));
            if ($read === false)
                throw new \Exception("read bytes error", 20001);
            if (strlen($read) == 0 && $i++ > FPNN_SOCKET_READ_RETRY)
                throw new \Exception("read empty bytes", 20001);
            $buf .= $read;
        }
        return $buf;
    }

    public function sendQuest($method, array $params, $oneway = false)
    {
        $quest = new Quest($method, $params, $oneway);

        if (is_null($this->socket) || is_bool($this->socket))
            $this->reconnectServer();

        $st = $quest->raw();

        if ($this->isEncryptor && $method != "*key") {
            $st = pack("VA*", strlen($st), $this->encrypt($st, true));
        }

        $length = strlen($st);

        while ($length > 0) {
            $sent = @fwrite($this->socket, $st, $length);
            if ($sent === false) {
                if ($this->autoReconnect && $this->retryTimes < FPNN_SOCKET_READ_RETRY) {
                    ++$this->retryTimes;
                    $this->reconnectServer();
                    $length = strlen($st);
                    continue;
                } else {
                    $this->retryTimes = 0;
                    throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
                }
            }
            if ($sent < $length) {
                // If not sent the entire message.
                // Get the part of the message that has not yet been sented as message
                $st = substr($st, $sent);
            }
            $length -= $sent;
        }

        if ($oneway)
            return;                // one way methods has no response.

        $this->canEncryptor = false;

        // read server response

        $arr = array();

        try {
            if ($this->isEncryptor) {
                $buf = $this->readBytes(4);
                $arr = unpack("Vlen", $buf);
                $buf = $this->readBytes($arr['len']);
                $buf = $this->encrypt($buf, false);
                $arr = unpack("A4magic/Cversion/Cflag/Cmtype/Css/Vpsize/VseqNum/A*payload", $buf);
            } else {
                $buf = $this->readBytes(16); // header size + sequence number
                $arr = unpack("A4magic/Cversion/Cflag/Cmtype/Css/Vpsize/VseqNum", $buf);
            }
        } catch (\Exception $e) {
            if ($this->autoReconnect && $this->retryTimes < FPNN_SOCKET_READ_RETRY) {
                ++$this->retryTimes;
                $this->reconnectServer();
                return $this->sendQuest($method, $params, $oneway);
            } else {
                throw $e;
            }
        }

        $this->retryTimes = 0;

        if ($arr["seqNum"] != $quest->getSeqNum()) {
            throw new \Exception("Server returned unmatched seqNum, quest seqNum: "
                . $quest->getSeqNum() . ", server returned seqNum: " . $arr["seqNum"], FPNN_PHP_SEQNUM_ERROR);
        }

        $payload = "";
        if ($this->isEncryptor)
            $payload = $arr['payload'];
        else
            $payload = $this->readBytes($arr["psize"]);

        $answer = msgpack_unpack($payload);
        if ($answer == NULL)
            throw new \Exception("msgpack unpack error while unpack data: " . $payload, FPNN_PHP_MSGPACK_UNPACK_ERROR);
        if ($arr["ss"]) {
            $e = new \Exception($answer["ex"], $answer["code"]);
            throw $e;
        }

        if (is_object($answer))
            $answer = (array)$answer;

        return $answer;
    }
}