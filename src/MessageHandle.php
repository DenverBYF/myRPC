<?php
/**
 * Created by PhpStorm.
 * User: denverb
 * Date: 18/4/2
 * Time: 下午7:58
 */


/*
 * 包处理接口,可自行实现以扩展解析方式。
 * input() --> 处理缓冲区内容
 * en/deCode --> 编码解码包内容
 * */
interface Strategy
{
	public function input($buffer);
	public function enCode($buffer);
	public function deCode($buffer);
}


/*
 * 利用结尾添加字符串+JSON编码的方式实现TCP包内容解析
 * */
class MessageUseSpecialChar implements Strategy
{
	public function input($buffer)
	{
		//获取换行符位置
		$index = strpos($buffer, "\n");

		if ($index === false) {
			return 0;
		}

		return $index + 1;
	}

	public function enCode($buffer)
	{
		return json_encode($buffer)."\n";
	}

	public function deCode($buffer)
	{
		return json_decode(trim($buffer), true);
	}

}

/*
 * 使用首部十个字节记录包长度解决粘包问题,JSON编码信息
 * */
class MessageUseDataLength implements Strategy
{
	public function input($buffer)
	{
		//获取前十个字节,表示数据长度
		$len = substr($buffer, 0, 10);
		//去除填充的0
		$num = intval(ltrim($len, '0'));

		$bufferLen = strlen($buffer);

		//数据长度不足,返回0,继续等待下一个数据包
		if($buffer < $num) {
			return 0;
		}

		//返回结束位置
		return $bufferLen;
	}

	public function enCode($buffer)
	{
		if (is_array($buffer)) {
			$buffer = json_encode($buffer);
		}
		$len = strlen($buffer);
		//填充0
		$data = sprintf("%010s", $len);
		return $data.$buffer;
	}

	public function deCode($buffer)
	{
		$data = substr($buffer, 10);
		$jsonData = json_decode($data, true);
		return $jsonData?? $data;
	}
}


/*
 * 环境角色
 * */
class TcpMsg
{
	public $_strategy, $_buffer;

	public function __construct(Strategy $strategy, $buffer)
	{
		$this->_strategy = $strategy;
		$this->_buffer = $buffer;
	}

	public function input()
	{
		return $this->_strategy->input($this->_buffer);
	}

	public function enCode($buffer)
	{
		return $this->_strategy->enCode($buffer);
	}

	public function deCode($buffer)
	{
		return $this->_strategy->deCode($buffer);
	}

}