<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }


// ブロッククラス
class GifDOM_blk extends GifDOM
{
	// 定数
	
	// プロテクテッドメンバ
		protected $_type = 0;
		protected $_blockStructure = null;
	
	// コンストラクタ
		protected function __construct($type, GifDOM $doc) {
			parent::__construct($doc);
			
			$this->_type = $type;
			$this->_parent = $doc;
		}
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			//dv($this->_blockStructure);
			if(! is_array($this->_blockStructure)) {
				throw new GifDOM_NotImplementedException(sprintf('%s::%s', get_class($this), __FUNCTION__));
			}
			
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$ret = $parser->ParseStructure($bs);
			foreach(get_object_vars($ret) as $k=>$v) {
				$this->$k = $v;
			}
			return $this;
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			if(! is_array($this->_blockStructure)) {
				throw new GifDOM_NotImplementedException(sprintf('%s::%s', get_class($this), __FUNCTION__));
			}
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$parser->setReturnObject((object)get_object_vars($this));
			$parser->BuildStructure($bs);
		}
		protected function _SaveBin(GifDOM_BitStream $bs) {
			return $this->_BuildBin($bs);
		}
	
	// パブリックメソッド
		public function GetType() {
			return $this->_type;
		}
		public function remove() {
			$this->GetDocument()->removeBlock($this);
		}
		public function Parse(GifDOM_BitStream $bs) {
			return $this->_ParseBin($bs);
		}
		public function Save() {
			$bs = new GifDOM_BitStream('');
			$this->_SaveBin($bs);
			$bs->Seek(0, SEEK_SET);
			return $bs->GetContents();
		}
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_parent'] = 'exclude';
			$prop_opts['_blockStructure'] = 'exclude';
			return parent::print_r($retstr, $prop_opts);
		}
}
class GifDOM_blk_Image extends GifDOM_blk
{
	// プロテクテッドメンバ
		protected $_blockStructure = array(
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_leftPosition'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_topPosition'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_width'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_height'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CONVERT_BITS, array(
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, 'hasColorTable'),
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, '_InterlaceFlag'),
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, '_SortFlag'),
				array(HA_BitStreamParser::TYPE_BITS2, HA_BitStreamParser::STORE, '_Reserved1'),
				array(HA_BitStreamParser::TYPE_BITS3, HA_BitStreamParser::STORE, 'sizeColorTable'),
			)),
		);
		protected $_leftPosition = 0;
		protected $_topPosition = 0;
		protected $_width = 0;
		protected $_height = 0;
		protected $_InterlaceFlag;
		protected $_SortFlag;
		protected $_Reserved1;
		protected $_localPallet;
		protected $_LZWMinimumCodeSide;
		protected $_data = '';
	
	// コンストラクタ
		protected function __construct($type, GifDOM $doc) {
			parent::__construct($type, $doc);
			
			$this->_localPallet = new GifDOM_ColorTable();
		}
		public function __clone() {
			$this->_localPallet = clone $this->_localPallet;
		}
	
	// パブリックメソッド
		public function GetLeftPosition() { return $this->_leftPosition; }
		public function SetLeftPosition($v) { settype($v, 'integer'); $this->_leftPosition = $v; }
		public function GetTopPosition() { return $this->_topPosition; }
		public function SetTopPosition($v) { settype($v, 'integer'); $this->_topPosition = $v; }
		public function GetWidth() { return $this->_width; }
		public function SetWidth($v) { settype($v, 'integer'); $this->_width = $v; }
		public function GetHeight() { return $this->_height; }
		public function SetHeight($v) { settype($v, 'integer'); $this->_height = $v; }
		public function GetLZWMinimumCodeSide() { return $this->_LZWMinimumCodeSide; }
		public function SetLZWMinimumCodeSide($v) { settype($v, 'integer'); $this->_LZWMinimumCodeSide = $v; }
		public function GetData() { return $this->_data; }
		public function SetData($v) { settype($v, 'string'); $this->_data = $v; }
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			$this->_localPallet = new GifDOM_ColorTable();
			$this->_data = '';
			
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$ret = $parser->ParseStructure($bs);
			
			$hasColorTable = $ret->hasColorTable; unset($ret->hasColorTable);
			$sizeColorTable = $ret->sizeColorTable; unset($ret->sizeColorTable);
			
			foreach(get_object_vars($ret) as $k=>$v) {
				$this->$k = $v;
			}
			
			if($hasColorTable)
			{
				$this->_localPallet = new GifDOM_ColorTable();
				for($i = 0; $i < $sizeColorTable; $i++)
				{
					$this->_localPallet->add(new GifDOM_pallet($bs->readUI8(), $bs->readUI8(),$bs->readUI8()));
				}
			}
			
			$this->_LZWMinimumCodeSide = $bs->readUI8();
			
			while(1)
			{
				$size = $bs->readUI8();
				if($size == 0) break;
				
				$this->_data .= $bs->readString($size);
			}
			
			return $this;
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			// スクリーンフラグの処理
			$hasColorTable = count($this->_localPallet) > 0;
			$sizeColorTable = count($this->_localPallet);
			
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$obj = (object)get_object_vars($this);
			$obj->hasColorTable = $hasColorTable;
			$obj->sizeColorTable = $sizeColorTable;
			$parser->setReturnObject($obj);
			$parser->BuildStructure($bs);
			
			foreach($this->_localPallet as $plt)
			{
				$bs->writeString($plt->Save());
			}
			
			$bs->writeUI8($this->_LZWMinimumCodeSide);
			
			foreach(str_split($this->_data, 255) as $data)
			{
				$bs->writeUI8(strlen($data));
				$bs->writeString($data);
			}
			$bs->writeUI8(0);
		}
	
	// パブリックメソッド
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_data'] = 'bin2hex';
			return parent::print_r($retstr, $prop_opts);
		}
}


/***** 拡張ブロック *****/
class GifDOM_blk_Extension extends GifDOM_blk
{
	// スタティックメンバ
		const BLK_PLAIN_TEXT_EXTENSION		= 0x01;
		const BLK_GRAPHIC_CONTROL_EXTENSION	= 0xF9;
		const BLK_COMMENT_EXTENSION			= 0xFE;
		const BLK_APPLICATION_EXTENSION 	= 0xFF;
		static $_blkClasses = array(
			self::BLK_PLAIN_TEXT_EXTENSION		=> 'GifDOM_blk_PlainTextExtension',
			self::BLK_GRAPHIC_CONTROL_EXTENSION	=> 'GifDOM_blk_GraphicControlExtension',
			self::BLK_COMMENT_EXTENSION			=> 'GifDOM_blk_CommentExtension',
			self::BLK_APPLICATION_EXTENSION		=> 'GifDOM_blk_ApplicationExtension',
		);
	
	// プロテクテッドメンバ
		protected $_extType;
	
	// パブリックメソッド
		/**
		 * 拡張ブロックのインスタンスを生成する
		 */
		public function CreateExtensionBlockClass($type) {
			if(isset(self::$_blkClasses[$type]) && class_exists(self::$_blkClasses[$type]))
				$classname = self::$_blkClasses[$type];
			else
				throw new GifDOM_NotImplementedException(sprintf('Extension block type [0x%02X] is unknown.', $type));
			$blk = GifDOM::_CreateInstance($classname, GifDOM::BLK_EXTENSION, $this->_parent);
			$blk->_extType = $type;
			
			return $blk;
		}
		public function GetExtensionType() {
			return $this->_extType;
		}
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			if(get_class($this) != __CLASS__) {
				return parent::_ParseBin($bs);
			}
			
			// 拡張ブロックタイプの読み込み
			$type = $bs->readUI8();
			if(! isset(static::$_blkClasses[$type]))
			{
				throw new GifDOM_InvalidDataException(sprintf('Extension block type [0x%02X] is unknown.', $type));
			}
			
			$blk = $this->CreateExtensionBlockClass($type, $this);
			$blk = $blk->_ParseBin($bs);
			
			return $blk;
		}
		final protected function _SaveBin(GifDOM_BitStream $bs) {
			$bs->writeUI8($this->_extType);
			return $this->_BuildBin($bs);
		}
}
class GifDOM_blk_PlainTextExtension extends GifDOM_blk_Extension
{
	// スタティックメンバ
	
	// プロテクテッドメンバ
		protected $_blockStructure = array(
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CHECK_NUM, 12),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_TextGridLeftPosition'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_TextGridTopPosition'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_TextGridWidth'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_TextGridHeight'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_CharacterCellWidth'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_CharacterCellHeight'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_TextForegroundColorIndex'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_TextBackgroundColorIndex'),
		);
		protected $_TextGridLeftPosition = 0;
		protected $_TextGridTopPosition = 0;
		protected $_TextGridWidth = 0;
		protected $_TextGridHeight = 0;
		protected $_CharacterCellWidth = 0;
		protected $_CharacterCellHeight = 0;
		protected $_TextForegroundColorIndex = 0;
		protected $_TextBackgroundColorIndex = 0;
		protected $_PlainTextData = null;
	
	// コンストラクタ
		protected function __construct($type, GifDOM_document $doc) {
			parent::__construct($type, $doc);
		}
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			$this->_PlainTextData = '';
			
			parent::_ParseBin($bs);
			
			while(1)
			{
				$size = $bs->readUI8();
				// 2番目以降のブロックは可変長
				if($size == 0) break;
				
				$this->_PlainTextData .= $bs->readString($size);
			}
			
			return $this;
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			parent::_BuildBin($bs);
			
			foreach(str_split($this->_PlainTextData, 255) as $data)
			{
				$bs->writeUI8(strlen($data));
				$bs->writeString($data);
			}
			$bs->writeUI8(0);
			
			return $this;
		}
	
	// パブリックメソッド
}
class GifDOM_blk_GraphicControlExtension extends GifDOM_blk_Extension
{
	// プロテクテッドメンバ
		protected $_blockStructure = array(
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CHECK_NUM, 4),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CONVERT_BITS, array(
				array(HA_BitStreamParser::TYPE_BITS3, HA_BitStreamParser::STORE, '_reserved1'),
				array(HA_BitStreamParser::TYPE_BITS3, HA_BitStreamParser::STORE, '_DisposalMethod'),
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, '_UserInputFlag'),
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, '_TransparentColorFlag'),
			)),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_DelayTime'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_TransparentColorIndex'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CHECK_NUM, 0),
		);
		protected $_reserved1 = 0;
		protected $_DisposalMethod = 0;
		protected $_UserInputFlag = 0;
		protected $_TransparentColorFlag = 0;
		protected $_DelayTime = 0;
		protected $_TransparentColorIndex = 0;
	
	// パブリックメソッド
		public function GetDisposalMethod() { return $this->_DisposalMethod; }
		public function SetDisposalMethod($v) { settype($v, 'integer'); $this->_DisposalMethod = $v; }
		public function GetUserInputFlag() { return $this->_UserInputFlag; }
		public function SetUserInputFlag($v) { settype($v, 'boolean'); $this->_UserInputFlag = (int)$v; }
		public function GetTransparentColorFlag() { return $this->_TransparentColorFlag; }
		public function SetTransparentColorFlag($v) { settype($v, 'boolean'); $this->_TransparentColorFlag = (int)$v; }
		public function GetDelayTime() { return $this->_DelayTime; }
		public function SetDelayTime($v) { settype($v, 'integer'); $this->_DelayTime = $v; }
		public function GetTransparentColorIndex() { return $this->_TransparentColorIndex; }
		public function SetTransparentColorIndex($v) { settype($v, 'integer'); $this->_TransparentColorIndex = $v; }
	
	// プロテクテッドメソッド
}
class GifDOM_blk_CommentExtension extends GifDOM_blk_Extension
{
	// スタティックメンバ
	
	// プロテクテッドメンバ
		protected $_Comment = '';
	
	// コンストラクタ
		protected function __construct($type, GifDOM_document $doc) {
			parent::__construct($type, $doc);
		}
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			$this->_Comment = '';
			
			while(1)
			{
				$size = $bs->readUI8();
				// 2番目以降のブロックは可変長
				if($size == 0) break;
				
				$this->_Comment .= $bs->readString($size);
			}
			
			return $this;
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			foreach(str_split($this->_Comment, 255) as $data)
			{
				$bs->writeUI8(strlen($data));
				$bs->writeString($data);
			}
			$bs->writeUI8(0);
			
			return $this;
		}
	
	// パブリックメソッド
		public function GetComment() { return $this->_Comment; }
		public function SetComment($v) { settype($v, 'string'); $this->_Comment = $v; }
}
class GifDOM_blk_ApplicationExtension extends GifDOM_blk_Extension
{
	// スタティックメンバ
	
	// プロテクテッドメンバ
		protected $_blockStructure = array(
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CHECK_NUM, 11),
			array(HA_BitStreamParser::TYPE_STRING, 8, HA_BitStreamParser::STORE, '_ApplicationIdentifier'),
			array(HA_BitStreamParser::TYPE_STRING, 3, HA_BitStreamParser::STORE, '_ApplicationAuthenticationCode'),
		);
		protected $_ApplicationIdentifier = '';
		protected $_ApplicationAuthenticationCode = '';
		protected $_ApplicationData = '';
	
	// コンストラクタ
		protected function __construct($type, GifDOM_document $doc) {
			parent::__construct($type, $doc);
		}
	
	// プロテクテッドメソッド
		protected function _ParseBin(GifDOM_BitStream $bs) {
			$this->_ApplicationData = '';
			
			parent::_ParseBin($bs);
			
			while(1)
			{
				$size = $bs->readUI8();
				// 2番目以降のブロックは可変長
				if($size == 0) break;
				
				$this->_ApplicationData .= $bs->readString($size);
			}
			
			return $this;
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			parent::_BuildBin($bs);
			
			foreach(str_split($this->_ApplicationData, 255) as $data)
			{
				$bs->writeUI8(strlen($data));
				$bs->writeString($data);
			}
			$bs->writeUI8(0);
			
			return $this;
		}
	
	// パブリックメソッド
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_ApplicationData'] = 'bin2hex';
			return parent::print_r($retstr, $prop_opts);
		}
}
