<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_document extends GifDOM {
	// プロテクテッドメンバ
		protected $_blockStructure = array(
			array(HA_BitStreamParser::TYPE_STRING, 3, HA_BitStreamParser::CHECK_STRING, 'GIF'),
			array(HA_BitStreamParser::TYPE_STRING, 3, HA_BitStreamParser::STORE, '_version'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_width'),
			array(HA_BitStreamParser::TYPE_UI16, HA_BitStreamParser::STORE, '_height'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::CONVERT_BITS, array(
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, 'hasGlobalPallet'),
				array(HA_BitStreamParser::TYPE_BITS3, HA_BitStreamParser::STORE, '_bitsPerPixel'),
				array(HA_BitStreamParser::TYPE_BITS1, HA_BitStreamParser::STORE, 'sortedPallet'),
				array(HA_BitStreamParser::TYPE_BITS3, HA_BitStreamParser::STORE, 'globalPalletSize'),
			)),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_bgColorIndex'),
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_aspectRatio'), // アスペクト比 0以外の時、縦:横 = ($aspectRatio + 15) : 64。0の時は 1:1

		);
		protected $_version = GifDOM::VERSION_89A;
		protected $_width = 0;
		protected $_height = 0;
		protected $_bitsPerPixel = 8;
		protected $_bgColorIndex = 0;
		protected $_aspectRatio = 0;
		protected $_globalPallet;
		protected $_blocks;
	
	// コンストラクタ
		protected function __construct(GifDOM $parent = null)
		{
			if(! is_null($parent)) {
				parent::__construct($parent);
			}
			
			$this->_globalPallet= new GifDOM_ColorTable();
			$this->_blocks= new GifDOM_Blocks($this);
		}
		public function __clone() {
			$this->_globalPallet = clone $this->_globalPallet;
			$this->_blocks = clone $this->_blocks;
		}
	
	// パブリックメソッド
		public function GetDocument() {
			if($this->_parent instanceof GifDOM_document)
				return $this->_parent;
			else if(! is_null($this->_parent) && $this->_parent !== $this)
				return $this->_parent->GetDocument();
			else
				return $this;
		}
		/**
		 * タグノードのインスタンスを生成する
		 */
		public function CreateBlockClass($type) {
			if($type == self::BLK_EXTENSION) {
				throw new GifDOM_MissImplementationException('Cannot create extension block by this method. Use CreateExtensionBlockClass.');
			}
			
			if(isset(self::$_blkClasses[$type]) && class_exists(self::$_blkClasses[$type]))
				$classname = self::$_blkClasses[$type];
			else
				throw new GifDOM_NotImplementedException(sprintf('Block type [0x%02X] is unknown.', $type));
			return GifDOM::_CreateInstance($classname, $type, $this);
		}
		public function CreateExtensionBlockClass($type) {
			$ext = GifDOM::_CreateInstance('GifDOM_blk_Extension', self::BLK_EXTENSION, $this);
			
			return $ext->CreateExtensionBlockClass($type);
		}
		/**
		 * Gifデータを出力する
		 * @return string
		 */
		public function Save() {
			$bs = new GifDOM_BitStream('');
			$this->_SaveBin($bs);
			$bs->Seek(0, SEEK_SET);
			return $bs->GetContents();
		}
		/**
		 * _blocksを取得する
		 */
		public function GetBlocks() {
			return $this->_blocks;
		}
		public function GetWidth() { return $this->_width; }
		public function SetWidth($v) { settype($v, 'integer'); $this->_width = (int)$v; }
		public function GetHeight() { return $this->_height; }
		public function SetHeight($v) { settype($v, 'integer'); $this->_height = (int)$v; }
		public function GetBgColorIndex() { return $this->_bgColorIndex; }
		public function SetBgColorIndex($v) { settype($v, 'integer'); $this->_bgColorIndex = (int)$v; }
		/**
		 * フレーム数を取得する
		 * GifDOM_blk_Imageの数を数える
		 * @return int
		 */
		public function getFrameCount() {
			return count($this->GetBlocks()->GetChildrenByType(GifDOM::BLK_IMAGE));
		}
		/**
		 * フレームを取得する
		 * @return GifDOM_frame
		 */
		public function getFrame($num) {
			$img = $this->GetBlocks()->GetChildrenByType(GifDOM::BLK_IMAGE)->getAt($num);
			if(! ($img instanceof GifDOM_blk_Image))
			{
				throw new GifDOM_InvalidArgumentsException();
			}
			return new GifDOM_frame($img);
		}
		/**
		 * フレームを削除する
		 * @param int $numFrame フレーム番号。0から。
		 */
		public function deleteFrame($numFrame) {
			$nowFrame = 0;
			foreach($this->GetBlocks() as $idx => $blk) {
				if($nowFrame == $numFrame)
				{
					// タグを削除
					switch($blk->GetType())
					{
					case self::BLK_IMAGE:
						break;
					case self::BLK_EXTENSION:
						switch($blk->GetExtensionType())
						{
						case GifDOM_blk_Extension::BLK_PLAIN_TEXT_EXTENSION:
						case GifDOM_blk_Extension::BLK_GRAPHIC_CONTROL_EXTENSION:
							break;
						}
						default:
							continue;
						break;
					default:
						continue;
					}
					
					$blk->remove();
				}
				if($blk->GetType() == self::BLK_IMAGE)
				{
					$nowFrame++;
				}
				if($nowFrame > $numFrame) break;
			}
		}
		/**
		 * ブロックを削除する
		 * @param GifDOM_blk $blk 削除するブロック
		 */
		public function removeBlock(GifDOM_blk $blk) {
			$this->removeBlockAt($this->GetBlocks()->getIndex($this->GetBlocks()->getKey($blk, true)));
		}
		/**
		 * ブロックを削除する
		 * @param int $index ブロックの位置
		 */
		public function removeBlockAt($index) {
			settype($idx, 'integer');
			$this->GetBlocks()->removeAt($index);
		}
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_blockStructure'] = 'exclude';
			return parent::print_r($retstr, $prop_opts);
		}
	
	// プロテクテッドメソッド
		/**
		 *
		 */
		protected function _ParseBin(GifDOM_BitStream $bs) {
			$this->_globalPallet= new GifDOM_ColorTable();
			$this->_blocks= new GifDOM_Blocks($this);
			
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$ret = $parser->ParseStructure($bs);
			
			$hasGlobalPallet = $ret->hasGlobalPallet; unset($ret->hasGlobalPallet);
			$sortedPallet = $ret->sortedPallet; unset($ret->sortedPallet);
			$globalPalletSize = $ret->globalPalletSize; unset($ret->globalPalletSize);
			
			foreach(get_object_vars($ret) as $k=>$v) {
				$this->$k = $v;
			}
			
			// スクリーンフラグの処理
			$this->_bitsPerPixel++;
			$globalPalletSize = pow(2, $globalPalletSize + 1);
			
			// 共通パレットの読み込み
			if($hasGlobalPallet)
			{
				for($i = 0; $i < $globalPalletSize; $i++)
				{
					$this->_globalPallet->add(new GifDOM_pallet($bs->readUI8(), $bs->readUI8(),$bs->readUI8()));
				}
			}
			
			// ブロックデータの読み込み
			while(! $bs->eof())
			{
				// ブロックタイプの読み込み
				$type = $bs->readUI8();
				if($type == 0x3B)
				{
					// 読み込み終了
					break;
				}
				if(! isset(static::$_blkClasses[$type]))
				{
					throw new GifDOM_InvalidDataException(sprintf('Block type [0x%02X] is unknown.', $type));
				}
				
				// CreateBlockClassで拡張ブロックを作れなくしたので、このメソッドはここでは使えない
				//$blk = $this->CreateBlockClass($type);
				
				if(isset(self::$_blkClasses[$type]) && class_exists(self::$_blkClasses[$type]))
					$classname = self::$_blkClasses[$type];
				else
					throw new GifDOM_NotImplementedException(sprintf('Block type [0x%02X] is unknown.', $type));
				$blk = GifDOM::_CreateInstance($classname, $type, $this);
				
				$blk = $blk->Parse($bs);
				if(! ($blk instanceof GifDOM_blk))
				{
					throw new GifDOM_MissImplementationException('GifDOM_blk::_ParseBin() must be return GifDOM_blk object.');
				}
				$this->GetBlocks()->add($blk);
			}
		}
		protected function _BuildBin(GifDOM_BitStream $bs) {
			// スクリーンフラグの処理
			$hasGlobalPallet = count($this->_globalPallet) > 0 ? 1 : 0;
			$sortedPallet = 0;
			$globalPalletSize = count($this->_globalPallet);
			
			$parser = new HA_BitStreamParser($this->_blockStructure);
			$obj = (object)get_object_vars($this);
			$obj->hasGlobalPallet = $hasGlobalPallet;
			$obj->sortedPallet = $sortedPallet;
			$obj->globalPalletSize = (int)log($globalPalletSize, 2) - 1;
			$obj->_bitsPerPixel--;
			$parser->setReturnObject($obj);
			$parser->BuildStructure($bs);
			
			// 共通パレットの読み込み
			foreach($this->_globalPallet as $plt)
			{
				$bs->writeString($plt->Save());
			}
			
			// ブロックデータの書き込み
			foreach($this->GetBlocks() as $block)
			{
				// ブロックタイプの書き込み
				$bs->writeUI8($block->GetType());
				$bs->writeString($block->Save());
			}
			$bs->writeUI8(0x3B);
		}
		protected function _SaveBin(GifDOM_BitStream $bs) {
			return $this->_BuildBin($bs);
		}
}

?>