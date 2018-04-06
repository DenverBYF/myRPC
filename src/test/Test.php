<?php


/**
 * Created by PhpStorm.
 * User: denverb
 * Date: 18/4/2
 * Time: 下午2:48
 */
class Test
{
	public function hello($param = [])
	{
		$ret = "hello";
		foreach ($param as $item) {
			$ret .= $item."  ";
		}
		return  ['a' => $ret];
	}
}