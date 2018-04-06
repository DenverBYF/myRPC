<?php
/**
 * Created by PhpStorm.
 * User: denverb
 * Date: 18/4/1
 * Time: 下午4:39
 */

namespace src\rpc;

require_once "MessageHandle.php";


class RPCClient
{
	private $_msgHandle;
	public function __construct($host, $port, $path, \Strategy $strategy = null)
	{
		$client = stream_socket_client("tcp://{$host}:{$port}", $err ,$errStr);

		if (!$client) {
			exit("$err : $errStr \n");
		}

		if(empty($strategy)) {
			$this->_msgHandle = new \MessageUseSpecialChar();
		} else {
			$this->_msgHandle = $strategy;
		}
		$tcpMsg = new \TcpMsg($this->_msgHandle, '');
		$data = ['class' => 'test', 'method' => 'hello', 'param' => ['test', 'test2']];

		fwrite($client, $tcpMsg->enCode($data));
		$ret = fread($client, 2048);
		print_r($tcpMsg->deCode($ret));
		fclose($client);
	}
}


$foo = new RPCClient('localhost', 12345, './');