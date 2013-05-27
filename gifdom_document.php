<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0�ʾ�Ǽ¹�
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// �ե�����̾��åѡ�
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_document extends GifDOM {
	// �ץ�ƥ��ƥåɥ���
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
			array(HA_BitStreamParser::TYPE_UI8, HA_BitStreamParser::STORE, '_aspectRatio'), // �����ڥ����� 0�ʳ��λ�����:�� = ($aspectRatio + 15) : 64��0�λ��� 1:1

		);
		protected $_version = GifDOM::VERSION_89A;
		protected $_width = 0;
		protected $_height = 0;
		protected $_bitsPerPixel = 8;
		protected $_bgColorIndex = 0;
		protected $_aspectRatio = 0;
		protected $_globalPallet;
		protected $_blocks;
	
	// ���󥹥ȥ饯��
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
	
	// �ѥ֥�å��᥽�å�
		public function GetDocument() {
			if($this->_parent instanceof GifDOM_document)
				return $this->_parent;
			else if(! is_null($this->_parent) && $this->_parent !== $this)
				return $this->_parent->GetDocument();
			else
				return $this;
		}
		/**
		 * �����Ρ��ɤΥ��󥹥��󥹤���������
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
		 * Gif�ǡ�������Ϥ���
		 * @return string
		 */
		public function Save() {
			$bs = new GifDOM_BitStream('');
			$this->_SaveBin($bs);
			$bs->Seek(0, SEEK_SET);
			return $bs->GetContents();
		}
		/**
		 * _blocks���������
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
		 * �ե졼������������
		 * GifDOM_blk_Image�ο��������
		 * @return int
		 */
		public function getFrameCount() {
			return count($this->GetBlocks()->GetChildrenByType(GifDOM::BLK_IMAGE));
		}
		/**
		 * �ե졼����������
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
		 * �ե졼���������
		 * @param int $numFrame �ե졼���ֹ档0���顣
		 */
		public function deleteFrame($numFrame) {
			$nowFrame = 0;
			foreach($this->GetBlocks() as $idx => $blk) {
				if($nowFrame == $numFrame)
				{
					// ��������
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
		 * �֥�å���������
		 * @param GifDOM_blk $blk �������֥�å�
		 */
		public function removeBlock(GifDOM_blk $blk) {
			$this->removeBlockAt($this->GetBlocks()->getIndex($this->GetBlocks()->getKey($blk, true)));
		}
		/**
		 * �֥�å���������
		 * @param int $index �֥�å��ΰ���
		 */
		public function removeBlockAt($index) {
			settype($idx, 'integer');
			$this->GetBlocks()->removeAt($index);
		}
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_blockStructure'] = 'exclude';
			return parent::print_r($retstr, $prop_opts);
		}
	
	// �ץ�ƥ��ƥåɥ᥽�å�
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
			
			// �����꡼��ե饰�ν���
			$this->_bitsPerPixel++;
			$globalPalletSize = pow(2, $globalPalletSize + 1);
			
			// ���̥ѥ�åȤ��ɤ߹���
			if($hasGlobalPallet)
			{
				for($i = 0; $i < $globalPalletSize; $i++)
				{
					$this->_globalPallet->add(new GifDOM_pallet($bs->readUI8(), $bs->readUI8(),$bs->readUI8()));
				}
			}
			
			// �֥�å��ǡ������ɤ߹���
			while(! $bs->eof())
			{
				// �֥�å������פ��ɤ߹���
				$type = $bs->readUI8();
				if($type == 0x3B)
				{
					// �ɤ߹��߽�λ
					break;
				}
				if(! isset(static::$_blkClasses[$type]))
				{
					throw new GifDOM_InvalidDataException(sprintf('Block type [0x%02X] is unknown.', $type));
				}
				
				// CreateBlockClass�ǳ�ĥ�֥�å�����ʤ������Τǡ����Υ᥽�åɤϤ����ǤϻȤ��ʤ�
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
			// �����꡼��ե饰�ν���
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
			
			// ���̥ѥ�åȤ��ɤ߹���
			foreach($this->_globalPallet as $plt)
			{
				$bs->writeString($plt->Save());
			}
			
			// �֥�å��ǡ����ν񤭹���
			foreach($this->GetBlocks() as $block)
			{
				// �֥�å������פν񤭹���
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