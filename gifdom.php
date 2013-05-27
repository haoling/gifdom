<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

abstract class GifDOM {
	// 定数
		const VERSION_87A = '87a';
		const VERSION_89A = '89a';
	
	// プロテクテッドメンバ
		protected $_parent = null;
	
	// スタティックメンバ
		const BLK_EXTENSION		= 0x21;
		const BLK_IMAGE			= 0x2C;
		static $_blkClasses = array(
			self::BLK_EXTENSION		=> 'GifDOM_blk_Extension',
			self::BLK_IMAGE			=> 'GifDOM_blk_Image',
		);
	
	// コンストラクタ
		protected function __construct(GifDOM $parent) {
			$this->SetParent($parent);
		}
		public function __destruct() {
			//GifDOM::debug('destruct');
		}
		static public function CreateDocument() {
			return new GifDOM_document();
		}
		
		static protected function _CreateInstance($classname, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null, $arg5 = null) {
			return new $classname($arg1, $arg2, $arg3, $arg4, $arg5);
		}
	
	/**
	 * gifのバイナリを解析します。
	 * @param string $data gifのバイナリデータ
	 */
	static public final function ParseGif($data) {
		// オブジェクトの生成
		$obj = new GifDOM_document();
		
		GifDOM::debug('Parse gif', true);
		
		// ビットストリームの作成
		$bs = new GifDOM_BitStream($data);
		
		$obj->_ParseBin($bs);
		
		GifDOM::debug('End [Parse Gif]', false);
		unset($bs);
		
		return $obj;
	}
	
	// パブリックメソッド
		public function GetParent() {
			return $this->_parent;
		}
		public function SetParent(GifDOM $parent) {
			$this->_parent = $parent;
		}
		public function GetDocument() {
			if($this->_parent instanceof GifDOM_document)
				return $this->_parent;
			else
				return $this->_parent->GetDocument();
		}
		public function GetRootDocument() {
			if(is_null($this->_parent))
				return $this;
			else
				return $this->_parent->GetRootDocument();
		}
	
	
	// デバッグ用
		static $__DebugShow = false;
		static $__DebugLog = null;
		static $__indent = '';
		static $__DebugMemory = 0;
		static $__DebugTime = 0;
		static public function debug_show($str) {
			if(self::$__DebugShow === false) return;
			echo self::$__indent.$str."\n";
		}
		static public function debug_init($show, $log = null) {
			self::$__DebugShow = $show;
			self::$__DebugLog = $log;
			
			if(self::$__DebugLog !== null) {
				file_put_contents(self::$__DebugLog, '');
				self::$__DebugMemory = memory_get_usage();
				list ($msec, $sec) = explode(' ', microtime());
				self::$__DebugTime = (float)$msec + (float)$sec;
			}
		}
		static public function debug_log($str) {
			if(self::$__DebugLog === null) return;
			
			list ($msec, $sec) = explode(' ', microtime());
			$microtime = (float)$msec + (float)$sec;
			$microtime -= self::$__DebugTime;
			
			$memory = memory_get_usage() - self::$__DebugMemory;
			
			$log = sprintf('[% 3.1f][% 10d] %s%s', $microtime, $memory, self::$__indent, $str);
			
			$fp = fopen(self::$__DebugLog, 'ab');
			fwrite($fp, $log."\n");
			fclose($fp);
		}
		static public function debug($str, $ind = null) {
			if(self::$__DebugShow === false && self::$__DebugLog === null) return;
			
			if($str !== null) {
				if(self::$__DebugShow !== false)
					self::debug_show($str);
				if(self::$__DebugLog !== null)
					self::debug_log($str);
			}
			
			if($ind === true)
				self::$__indent .= '    ';
			else if ($ind === false)
				self::$__indent = substr(self::$__indent, 0, strlen(self::$__indent)-4);
		}
		static public function debugf() {
			if(self::$__DebugShow === false && self::$__DebugLog === null) return;
			
			$params = func_get_args();
			$str = call_user_func_array("sprintf", $params);
			return GifDOM::debug($str);
		}
		
		static $__print_r_indent = '';
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_property_functions'] = 'exclude';
			if($retstr)
				return GifDOM::_print_r($this, $retstr, $prop_opts);
			else
				echo GifDOM::_print_r($this, $retstr, $prop_opts);
		}
		final static public function _print_r($target, $retstr, $prop_opts) {
			switch(strtolower(gettype($target))) {
			case 'boolean':
				return sprintf('bool(%s)', $target ? 'true' : 'false');
			
			case 'integer':
				return sprintf('int(%d)', $target);
			
			case 'double':
				return sprintf('double(%f)', $target);
			
			case 'string':
				if($prop_opts[0] == 'bin2hex')
					return sprintf('string(%d) "%s"', strlen($target), bin2hex($target));
				else
					return sprintf('string(%d) "%s"', strlen($target), $target);
			
			case 'null':
				return 'NULL';
			
			case 'array':
				$ret = '';
				GifDOM::_print_r_echo(null, true);
				foreach($target as $key => $value) {
					if($value instanceof GifDOM || $value instanceof GifDOM_ArrayList)
					{
						$ind = self::$__print_r_indent;
						self::$__print_r_indent = '';
						$str = $value->print_r(1);
						self::$__print_r_indent = $ind;
						$ret .= GifDOM::_print_r_echo('['.$key.'] => '.$str);
					}
					else
					{
						$ret .= GifDOM::_print_r_echo('['.$key.'] => '.GifDOM::_print_r($value, $retstr, $prop_opts));
					}
				}
				$ret = sprintf("Array (\n%s)", $ret);
				GifDOM::_print_r_echo(null, false);
				return $ret;
			
			case 'object':
				$ret = '';
				$obj = new ReflectionObject($target);
				
				GifDOM::_print_r_echo(null, true);
				
				foreach($obj->getProperties() as $prop) {
					$name = $prop->getName();
					if(isset($prop_opts[$name]) && $prop_opts[$name] == 'exclude') continue;
					if($prop->isStatic()) continue;
					$mod = array();
					if($prop->isPublic()) $mod[] = 'public';
					if($prop->isPrivate()) $mod[] = 'private';
					if($prop->isProtected()) $mod[] = 'protected';
					$value = $target->$name;
					if($value instanceof GifDOM || $value instanceof GifDOM_ArrayList)
					{
						$ind = self::$__print_r_indent;
						self::$__print_r_indent = '';
						$str = $value->print_r(1);
						self::$__print_r_indent = $ind;
						$ret .= GifDOM::_print_r_echo(sprintf('[%s:%s] => %s', $name, join(' ', $mod), $str));
					}
					else
					{
						$ret .= GifDOM::_print_r_echo(
							sprintf(
								'[%s:%s] => %s',
								$name,
								join(' ', $mod),
								GifDOM::_print_r(
									$value,
									$retstr,
									array(
										isset($prop_opts[$name]) ? $prop_opts[$name] : array()
									)
								)
							)
						);
					}
				}
				$ret = sprintf("%s (\n%s)", $obj->getName(), $ret);
				GifDOM::_print_r_echo(null, false);
				return $ret;
				
			default:
				var_dump('print_r must implements '.gettype($target)); exit;
			}
		}
		final static public function _print_r_echo($str, $ind = null) {
			$ret = '';
			
			if ($ind === false)
				self::$__print_r_indent = substr(self::$__print_r_indent, 0, strlen(self::$__print_r_indent)-4);
			
			if($str !== null)
				$ret = self::$__print_r_indent.join("\n".self::$__print_r_indent, explode("\n", $str))."\n";
			
			if($ind === true)
				self::$__print_r_indent .= '    ';
			
			return $ret;
		}
}

class GifDOM_pallet extends GifDOM
{
	// メンバ
		protected $_r;
		protected $_g;
		protected $_b;
	
	// コンストラクタ
		public function __construct($r = 0xFF, $g = 0xFF, $b = 0xFF)
		{
			$this->_r = $r;
			$this->_g = $g;
			$this->_b = $b;
		}
	
	// パブリックメソッド
		public function Save() {
			return chr($this->_r).chr($this->_g).chr($this->_b);
		}
}



// インクルード
if(! class_exists('GifDOM_BitStream')) {
	require(_f('ha_bitstream.php'));
	require(_f('gifdom_bitstream.php'));
}
require(_f('gifdom_arraylist.php'));
require(_f('gifdom_exception.php'));
require(_f('gifdom_blocks.php'));
require(_f('gifdom_document.php'));
require(_f('gifdom_frame.php'));

require(_f('gifdom_resizer.php'));
