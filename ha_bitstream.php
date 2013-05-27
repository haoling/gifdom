<?php

/*------------------------------------------

[Depends]

[Document]
汎用ビットストリームクラス

[Version]
2012-09-22	1.00	汎用化

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class HA_BitStream {
	// 定数
		const BYTEORDER_BIG = 0;
		const BYTEORDER_LITTLE = 1;
		const BYTEORDER_NETWORK = self::BYTEORDER_BIG;
	
	// スタティックメンバ
		static $BYTEORDER = self::BYTEORDER_NETWORK;
	
	// プロテクテッドメンバ
		protected $_fp = null;
	
	public function __construct($data) {
		$this->_fp = fopen('php://temp', 'w+');
		if($this->_fp === false)
			throw new Exception('IO exception');
		if(fwrite($this->_fp, $data) === false)
			throw new Exception('IO exception');
		fseek($this->_fp, 0, SEEK_SET);
	}
	public function __destruct() {
		fclose($this->_fp);
		$this->_fp = null; unset($this->_fp);
	}
	
	public function GetPos() {
		return ftell($this->_fp);
	}
	
	public function EOF() {
		return feof($this->_fp);
	}
	
	public function GetContents() {
		return stream_get_contents($this->_fp, -1, 0);
	}
	
	public function getLength() {
		$stat = fstat($this->_fp);
		return $stat['size'];
	}
	
	public function Seek($byte, $mode = SEEK_CUR) {
		$pos = ftell($this->_fp);
		$len = $this->getLength();
		$seek = $pos;
		
		switch($mode) {
		case SEEK_SET:
			$seek = $byte;
			break;
		case SEEK_CUR:
			$seek += $byte;
			break;
		case SEEK_END:
			$seek = $len + $byte;
			break;
		}
		if($seek < 0)
			throw new Exception('Invalid arguments');
		
		if($seek > $len) {
			fseek($this->_fp, 0, SEEK_END);
			fwrite($this->_fp, str_repeat(chr(0), $seek - $len));
		} else {
			fseek($this->_fp, $seek, SEEK_SET);
		}
	}
	
	public function readString($len) {
		$ret = stream_get_contents($this->_fp, $len);
		if($ret === false || strlen($ret) < $len)
			throw new Exception('IO exception: Read '.strlen($ret).' bytes.');
		return $ret;
	}
	public function readUI8() {
		return ord($this->readString(1));
	}
	public function readUI16() {
		$str = $this->readString(2);
		if(static::$BYTEORDER == self::BYTEORDER_LITTLE) $str = strrev($str);
		return (ord($str[0]) << 8) + (ord($str[1]));
	}
	public function readUI32() {
		$str = $this->readString(4);
		if(static::$BYTEORDER == self::BYTEORDER_LITTLE) $str = strrev($str);
		return (ord($str[0]) << 24) + (ord($str[1]) << 16) + (ord($str[2]) << 8) + (ord($str[3]));
	}
	public function readSI8() {
		$ret = $this->readUI8();
		if($ret > 0x7F) $ret -= 0xFF;
		return $ret;
	}
	public function readSI16() {
		$ret = $this->readUI16();
		if($ret > 0x7FFF) $ret -= 0xFFFF;
		return $ret;
	}
	public function readSI32() {
		$ret = $this->readUI32();
		if($ret > 0x7FFFFFFF) $ret -= 0xFFFFFFFF;
		return $ret;
	}
	
	public function writeString($str) {
		$ret = fwrite($this->_fp, $str);
		if($ret === false)
			throw new Exception('IO exception');
		return $ret;
	}
	public function writeUI8($num) {
		return $this->writeString(chr($num));
	}
	public function writeUI16($num) {
		$str = chr(($num >> 8) & 0xFF).chr(($num) & 0xFF);
		if(static::$BYTEORDER == self::BYTEORDER_LITTLE) $str = strrev($str);
		return $this->writeString($str);
	}
	public function writeUI32($num) {
		$str = chr(($num >> 24) & 0xFF).chr(($num >> 16) & 0xFF).chr(($num >> 8) & 0xFF).chr(($num) & 0xFF);
		if(static::$BYTEORDER == self::BYTEORDER_LITTLE) $str = strrev($str);
		return $this->writeString($str);
	}
	public function writeSI8($num) {
		if($num > 0x7F) $num = 0x7F;
		if($num < 0-0x80) $num = 0-0x80;
		if($num < 0) $num += 0xFF;
		return $this->writeString(pack('C', $num));
	}
	public function writeSI16($num) {
		if($num > 0x7FFF) $num = 0x7FFF;
		if($num < 0-0x8000) $num = 0-0x8000;
		if($num < 0) $num += 0xFFFF;
		return $this->writeString(pack('n', $num));
	}
	public function writeSI32($num) {
		if($num > 0x7FFFFFFF) $num = 0x7FFFFFFF;
		if($num < 0-0x80000000) $num = 0-0x80000000;
		if($num < 0) $num += 0xFFFFFFFF;
		return $this->writeString(pack('N', $num));
	}
	
	/*
	 * 先頭ビットから順に読む
	 * 使い方：
	 * $flag1 = ($byte >> 6) & 0x03;	// 先頭2ビット
	 * $flag2 = ($byte >> 2) & 0x0F;	// 次の4ビット
	 * $flag3 = ($byte >> 1) & 0x01;	// 次の1ビット
	 * $flag4 = ($byte     ) & 0x01;	// 最後1ビット
	 * 
	 * list($flag1, $flag2, $flag3, $flag4) = $bs->convertByteToBits($byte, array(2, 4, 1, 1));
	 */
	static public function convertByteToBits($byte, $bits)
	{
		settype($byte, 'integer');
		
		if(! is_array($bits))
		{
			// 可変引数を取得する
			$bits = func_get_args();
			array_shift($bits);
		}
		
		// 合計8ビットになるようにする
		if(array_sum($bits) > 8)
		{
			throw new Exception('Invalid arguments: $bits must be total 8 bits.');
		}
		if(array_sum($bits) < 8)
		{
			$bits[] = 8 - array_sum($bits);
		}
		
		$ret = array();
		// 最終側から処理する
		$bits = array_reverse($bits);
		
		foreach($bits as $bit)
		{
			if($bit <= 0)
			{
				throw new Exception('Invalid arguments: $bits each value must be over 0.');
			}
			
			switch($bit)
			{
			case 1:
				$ret[] = $byte & 0x01;
				$byte = $byte >> 1;
				break;
			case 2:
				$ret[] = $byte & 0x03;
				$byte = $byte >> 2;
				break;
			case 3:
				$ret[] = $byte & 0x07;
				$byte = $byte >> 3;
				break;
			case 4:
				$ret[] = $byte & 0x0F;
				$byte = $byte >> 4;
				break;
			case 5:
				$ret[] = $byte & 0x1F;
				$byte = $byte >> 5;
				break;
			case 6:
				$ret[] = $byte & 0x3F;
				$byte = $byte >> 6;
				break;
			case 7:
				$ret[] = $byte & 0x7F;
				$byte = $byte >> 7;
				break;
			case 8:
				$ret[] = $byte & 0xFF;
				$byte = $byte >> 8;
				break;
			}
		}
		
		return array_reverse($ret);
	}
	/*
	 * 先頭ビットから順に書く
	 * 使い方：
	 * $flag1 = ($byte >> 6) & 0x03;	// 先頭2ビット
	 * $flag2 = ($byte >> 2) & 0x0F;	// 次の4ビット
	 * $flag3 = ($byte >> 1) & 0x01;	// 次の1ビット
	 * $flag4 = ($byte     ) & 0x01;	// 最後1ビット
	 * 
	 * $bs->convertBitsToByte(array(2, 4, 1, 1), array(($flag1, $flag2, $flag3, $flag4)));
	 */
	static public function convertBitsToByte(array $bits, array $datas)
	{
		// 合計8ビットになるようにする
		if(array_sum($bits) > 8)
		{
			throw new Exception('Invalid arguments: $bits must be total 8 bits.');
		}
		if(array_sum($bits) < 8)
		{
			$bits[] = 8 - array_sum($bits);
		}
		
		if(count($bits) != count($datas))
		{
			throw new Exception('Invalid arguments: Parameter bits and bytes must has same size.');
		}
		
		$ret = 0;
		while(count($bits))
		{
			$bit = array_shift($bits);
			$data = array_shift($datas);
			
			if($bit <= 0)
			{
				throw new Exception('Invalid arguments: $bits each value must be over 0.');
			}
			
			switch($bit)
			{
			case 1:
				$ret = ($ret << 1) | ($data & 0x01);
				break;
			case 2:
				$ret = ($ret << 2) | ($data & 0x03);
				break;
			case 3:
				$ret = ($ret << 3) | ($data & 0x07);
				break;
			case 4:
				$ret = ($ret << 4) | ($data & 0x0F);
				break;
			case 5:
				$ret = ($ret << 5) | ($data & 0xF0);
				break;
			case 6:
				$ret = ($ret << 6) | ($data & 0xF3);
				break;
			case 7:
				$ret = ($ret << 7) | ($data & 0xF7);
				break;
			case 8:
				$ret = ($ret << 8) | ($data & 0xFF);
				break;
			}
		}
		
		return $ret;
	}
}

class HA_BitStreamParser
{
	// 定数
		// 自動構造解析用
			// 型指定子
				const TYPE_BITS1	= '#TYPE_BITS1#';
				const TYPE_BITS2	= '#TYPE_BITS2#';
				const TYPE_BITS3	= '#TYPE_BITS3#';
				const TYPE_BITS4	= '#TYPE_BITS4#';
				const TYPE_BITS5	= '#TYPE_BITS5#';
				const TYPE_BITS6	= '#TYPE_BITS6#';
				const TYPE_BITS7	= '#TYPE_BITS7#';
				const TYPE_BITS8	= '#TYPE_BITS8#';
				const TYPE_UI8		= '#TYPE_UI8#';
				const TYPE_UI16		= '#TYPE_UI16#';
				const TYPE_UI32		= '#TYPE_UI32#';
				const TYPE_SI8		= '#TYPE_SI8#';
				const TYPE_SI16		= '#TYPE_SI16#';
				const TYPE_SI32		= '#TYPE_SI32#';
				const TYPE_STRING	= '#TYPE_STRING#';
			// 行動指定子
				// 行動指定子を省略した場合、読み取った値は捨てられる。書き込み時には0または""が書き込まれる。
				const CHECK_NUM		= '#CEHCK_NUM#';		// 数値をチェックする。int 数値
				const CHECK_STRING	= '#CEHCK_STRING#';		// 文字列をチェックする。string 文字列
				const CONVERT_BITS	= '#CONVERT_BITS#';		// 数値をビットに変換する。array 構造解析配列
				const STORE			= '#STORE#';			// 値を変数に格納する。string 変数名
	
	// プロテクテッドメンバ
		protected $_structure = array();
		protected $_retObj;
	
	// コンストラクタ
		public function __construct(array $structure) {
			$this->_structure = $structure;
			$this->_retObj = new stdClass();
		}
	
	// パブリックメソッド
		public function setReturnObject($obj) {
			if(gettype($obj) != 'object') {
				throw new Exception('1st parameter must be object.');
			}
			$this->_retObj = $obj;
		}
		public function ParseStructure(HA_BitStream $bs) {
			$this->_ParseStructure($bs);
			return $this->_retObj;
		}
		public function BuildStructure(HA_BitStream $bs) {
			$this->_BuildStructure($bs);
		}
	
	// 内部メソッド
		protected function _ParseStructure(HA_BitStream $bs) {
			// 構造を自動解析する
			foreach($this->_structure as $stdef) {
				if(! is_array($stdef)) {
					throw new Exception('Structure parameters must be array.');
				}
				
				// 読み取りタイプ
				switch($type = array_shift($stdef)) {
				case self::TYPE_UI8:
				case self::TYPE_UI16:
				case self::TYPE_UI32:
				case self::TYPE_SI8:
				case self::TYPE_SI16:
				case self::TYPE_SI32:
					$method = 'read'.substr($type, strrpos($type, '_')+1, -1);
					$data = $bs->$method();
					break;
				case self::TYPE_STRING:
					$size = (int)array_shift($stdef);
					$data = $bs->readString($size);
					break;
				default:
					throw new Exception('Unknown structure data type: '.$type);
				}
				
				$this->_ParseStructure_action($data, $stdef);
			}
		}
		private function _ParseStructure_action($data, $stdef) {
			if(! isset($stdef[0])) {
				// 捨てる
				return;
			}
			
			// アクションを選択
			switch($action = array_shift($stdef)) {
			case self::CHECK_NUM:
				$param1 = (int)array_shift($stdef);
				settype($data, 'integer');
				if($data != $param1) {
					throw new Exception('Data check failed');
				}
				break;
			case self::CHECK_STRING:
				$param1 = (string)array_shift($stdef);
				settype($data, 'string');
				if($data != $param1) {
					throw new Exception('Data check failed');
				}
				break;
			case self::CONVERT_BITS:
				$param1 = array_shift($stdef);
				if(! is_array($param1)) {
					throw new Exception('CONVERT_BITS option must be array.');
				}
				
				// ビット数を確認
				$bits = array();
				foreach($param1 as $bstdef) {
					if(! is_array($bstdef)) {
						throw new Exception('Structure parameters must be array.');
					}
					
					switch($btype = array_shift($bstdef)) {
					case self::TYPE_BITS1: $bits[] = 1; break;
					case self::TYPE_BITS2: $bits[] = 2; break;
					case self::TYPE_BITS3: $bits[] = 3; break;
					case self::TYPE_BITS4: $bits[] = 4; break;
					case self::TYPE_BITS5: $bits[] = 5; break;
					case self::TYPE_BITS6: $bits[] = 6; break;
					case self::TYPE_BITS7: $bits[] = 7; break;
					case self::TYPE_BITS8: $bits[] = 8; break;
					default:
						throw new Exception('Invalid structure bits define: '.$btype);
					}
				}
				$bits = HA_BitStream::convertByteToBits($data, $bits);
				if(count($bits) != count($param1)) {
					throw new Exception('Parse bits failed');
				}
				
				foreach($param1 as $bstdef) {
					$btype = array_shift($bstdef);
					$data = array_shift($bits);
					$this->_ParseStructure_action($data, $bstdef);
				}
				break;
			case self::STORE:
				$param1 = array_shift($stdef);
				$this->_retObj->$param1 = $data;
				break;
			default:
				throw new Exception('Unknown structure action: '.$action);
			}
		}
		protected function _BuildStructure(HA_BitStream $bs) {
			// 構造を自動解析する
			foreach($this->_structure as $stdef) {
				if(! is_array($stdef)) {
					throw new Exception('Structure parameters must be array.');
				}
				
				$type = array_shift($stdef);
				
				// 読み取りタイプ
				switch($type) {
				case self::TYPE_UI8:
				case self::TYPE_UI16:
				case self::TYPE_UI32:
				case self::TYPE_SI8:
				case self::TYPE_SI16:
				case self::TYPE_SI32:
					$method = 'write'.substr($type, strrpos($type, '_')+1, -1);
					$data = $this->_BuildStructure_action($stdef);
					$bs->$method($data);
					break;
				case self::TYPE_STRING:
					$size = (int)array_shift($stdef);
					$data = $this->_BuildStructure_action($stdef);
					$data = substr(str_pad($data, $size, "\0"), 0, $size);
					$bs->writeString($data);
					break;
				default:
					throw new Exception('Unknown structure data type: '.$type);
				}
				
			}
		}
		protected function _BuildStructure_action($stdef) {
			if(! isset($stdef[0])) {
				return null;
			}
			
			// アクションを選択
			switch($action = array_shift($stdef)) {
			case self::CHECK_NUM:
				return (int)array_shift($stdef);
			case self::CHECK_STRING:
				return (string)array_shift($stdef);
			case self::CONVERT_BITS:
				$param1 = array_shift($stdef);
				if(! is_array($param1)) {
					throw new Exception('CONVERT_BITS option must be array.');
				}
				
				// ビット数を確認
				$bits = array();
				foreach($param1 as $bstdef) {
					if(! is_array($bstdef)) {
						throw new Exception('Structure parameters must be array.');
					}
					
					switch($btype = array_shift($bstdef)) {
					case self::TYPE_BITS1: $bits[] = 1; break;
					case self::TYPE_BITS2: $bits[] = 2; break;
					case self::TYPE_BITS3: $bits[] = 3; break;
					case self::TYPE_BITS4: $bits[] = 4; break;
					case self::TYPE_BITS5: $bits[] = 5; break;
					case self::TYPE_BITS6: $bits[] = 6; break;
					case self::TYPE_BITS7: $bits[] = 7; break;
					case self::TYPE_BITS8: $bits[] = 8; break;
					default:
						throw new Exception('Invalid structure bits define: '.$btype);
					}
				}
				
				$data = array();
				foreach($param1 as $bstdef) {
					$btype = array_shift($bstdef);
					$data[] = $this->_BuildStructure_action($bstdef);
				}
				
				return HA_BitStream::convertBitsToByte($bits, $data);
			case self::STORE:
				$param1 = array_shift($stdef);
				return $this->_retObj->$param1;
				break;
			default:
				throw new Exception('Unknown structure action: '.$action);
			}
		}
}
