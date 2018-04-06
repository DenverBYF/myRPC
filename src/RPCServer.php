<?php
/*/**
 * Created by PhpStorm.
 * User: denverb
 * Date: 18/4/1
 * Time: 下午4:39
 */

namespace src\rpc;

require_once "MessageHandle.php";

/*
 * RPC调用服务端,默认本地1234端口,本地目录
 * */
class RPCServer
{
	private $_serv;		//socket链接
	private $_tcpMsg;  //消息处理,解决TCP粘包等问题

	public function __construct($host = 'localhost', $port = 1234, $path = '/', \Strategy $strategy = null)
	{


		//创建一个socket
		$this->_serv = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if (FALSE === $this->_serv)
		{
			$errcode = socket_last_error();
			fwrite(STDERR, "socket create fail: " . socket_strerror($errcode));
			exit(-1);
		}

		//绑定ip地址及端口
		if (!socket_bind($this->_serv, $host, $port))
		{
			$errcode = socket_last_error();
			fwrite(STDERR, "socket bind fail: " . socket_strerror($errcode));
			exit(-1);
		}

		//允许多少个客户端来排队连接
		if (!socket_listen($this->_serv, 128))
		{
			$errcode = socket_last_error();
			fwrite(STDERR, "socket listen fail: " . socket_strerror($errcode));
			exit(-1);
		}

		//判断文件路径
		$realPath = realpath(__DIR__.$path);
		if($realPath == false || !file_exists($realPath)) {
			exit('path error');
		}

		//设置包处理策略
		if (empty($strategy)) {
			$this->_tcpMsg = new \MessageUseSpecialChar();
		} else {
			$this->_tcpMsg = $strategy;
		}

		//监听数组初始化
		$readSocks = [];
		$writeSocks = [];
		$exceptSocks = NULL;
		$readSocks[] = $this->_serv;

		//循环等待连接
		while (1) {

			$tmpRead = $readSocks;
			$tmpWrite = $writeSocks;

			//select实现I/O多路复用
			$count = socket_select($tmpRead, $tmpWrite, $exceptSocks, NULL);

			//循环处理监听数组
			foreach ($tmpRead as $client) {
				if($client == $this->_serv) {
					//获取客户端连接
					$connect = socket_accept($this->_serv);
					if($connect) {
						socket_getpeername($connect, $addr, $port);
						echo "connect ip:{$addr} port:{$port}";

						//加入监听
						$readSocks[] = $connect;
						$writeSocks[] = $connect;
					}
				} else {
					$recvBuffer = '';

					//包处理
					while (!isset($requestBuffer)) {
						$buffer = socket_read($client, 2048);
						var_dump($buffer);
						//处理策略
						$messageHandle = new \TcpMsg($this->_tcpMsg, $buffer);
						$currentPackageLength = 0;

						//连接断开处理
						if ($buffer == '' || $buffer === false) {
							echo "client done";
							if (feof($client) || !is_resource($client) || $buffer == false) {
								//移除监听
								unset($readSocks[array_search($client, $readSocks)]);
								unset($writeSocks[array_search($client, $writeSocks)]);
								//关闭连接
								socket_close($client);
								return ;
							}
						} else {
							$recvBuffer .= $buffer;
						}

						//循环处理粘包
						while ($recvBuffer !== '') {
							if ($currentPackageLength) {
								if ($currentPackageLength > strlen($recvBuffer)) {
									break;
								}
							} else {
								$currentPackageLength = $messageHandle->input();
								//无结束符
								if ($currentPackageLength === 0) {
									break;
								} elseif ($currentPackageLength > 0) {
									//不够
									if ($currentPackageLength > strlen($recvBuffer)) {
										break;
									}
								} else {
									//错误包
									echo("wrong package");
									socket_close($client);
									return ;
								}
							}

							//在此包结束时刚好获取到一个完整请求
							if (strlen($recvBuffer) === $currentPackageLength) {
								if (!isset($requestBuffer)) $requestBuffer = '';
								$requestBuffer .= $recvBuffer;

								//清缓冲区,结束循环处理
								$recvBuffer = '';
							} else {
								//获取包
								if (!isset($requestBuffer)) $requestBuffer = '';
								$requestBuffer .= substr($recvBuffer, 0, $currentPackageLength);

								//重置缓冲区
								$recvBuffer = substr($recvBuffer, $currentPackageLength);
							}
							//重置长度
							$currentPackageLength = 0;
						}
					}

					try {
						$data = $messageHandle->deCode($requestBuffer);
						//清除缓冲变量
						unset($requestBuffer);
						//调用类名
						$className = ucfirst($data['class']);
						//调用文件名
						$fileName = $realPath.'/'.$className.'.php';

						require_once $fileName;
						//生成实例
						$obj = new $className();

						$methodName = $data['method'];
						if(!isset($data['param'])) {

							$ret = $obj->$methodName();
						} else {
							$param = $data['param'];
							$ret = $obj->$methodName($param);
						}
					} catch (\Exception $e) {
						$ret = ["wrong message"];
					}

					//如果该client,则返回结果
					if (in_array($client, $tmpWrite)) {
						$returnData = $messageHandle->enCode($ret);
						var_dump($returnData);
						socket_write($client, $returnData);
					}
					//移除监听
					unset($readSocks[array_search($client, $readSocks)]);
					unset($writeSocks[array_search($client, $writeSocks)]);
				}
			}
		}
		//关闭socket连接
		socket_close($this->_serv);
	}
}


$message = new \MessageUseDataLength();

$a = new RPCServer('localhost', 12345, '/test');



