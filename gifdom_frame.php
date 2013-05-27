<?php

/*------------------------------------------

[Depends]

[Document]
GIF���˥᡼������1�ե졼�����Ū��������륯�饹

------------------------------------------*/

// PHP5.1.0�ʾ�Ǽ¹�
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// �ե�����̾��åѡ�
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_frame extends GifDOM_document {
	// �ץ�ƥ��ƥåɥ���
	
	// ���󥹥ȥ饯��
		protected function __construct(GifDOM_blk_Image $blk) {
			parent::__construct($blk);
		}
		public function __clone() {
			$newdoc = clone ($this->GetDocument());
			$this->SetParent($newdoc->GetFrame($this->GetFrameNumber())->GetParent());
		}
	
	// �ץ�ƥ��ƥåɥ᥽�å�
		protected function _SaveBin(GifDOM_BitStream $bs) {
			// ���Ū��Document��_blocks���֤������ƥ����֤���
			
			$doc = $this->GetDocument();
			$doc->_blocks->startTransaction();
			$doc->_blocks->exchangeArray($this->GetBlocks(true)->getData());
			
			try {
				$ret = $doc->_SaveBin($bs);
			} catch(Exception $e) {
				$doc->_blocks->rollbackTransaction();
				throw $e;
			}
			
			$doc->_blocks->rollbackTransaction();
			return $ret;
		}
	
	// �ѥ֥�å��᥽�å�
		public function SetParent(GifDOM $parent) {
			if(! ($parent instanceof GifDOM_blk_Image)) {
				throw new GifDOM_InvalidArgumentsException();
			}
			parent::SetParent($parent);
		}
		/**
		 * ���Υե졼����ֹ���������
		 * ������줿�ե졼��ξ����㳰�򥹥�����
		 */
		public function GetFrameNumber() {
			$imgs = $this->GetDocument()->GetBlocks()->GetChildrenByType(GifDOM::BLK_IMAGE);
			return $imgs->GetIndex($imgs->GetKey($this->GetParent(), true));
		}
		/**
		 * �ե졼���Ϣ����֥�å����������
		 * @param boolean $greed = false GIF���Τ˴ط�����֥�å���ޤ��
		 * @return GifDOM_Blocks_copied
		 */
		public function GetBlocks($greed = false) {
			$numFrame = $this->GetFrameNumber();
			$nowFrame = 0;
			$ret = new GifDOM_Blocks_copied($this->GetParent());
			foreach($this->GetDocument()->_blocks as $idx => $blk) {
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
						default:
							if(! $greed) continue 3;
						}
						break;
					default:
						if(! $greed) continue 2;
					}
					
					$ret[] = $blk;
				}
				if($blk->GetType() == self::BLK_IMAGE)
				{
					$nowFrame++;
				}
				if($nowFrame > $numFrame && ! $greed) break;
			}
			return $ret;
		}
		/**
		 * �ե졼���������
		 */
		public function remove() {
			foreach($this->GetBlocks() as $blk) {
				$blk->remove();
			}
		}
		public function print_r($retstr = false, $prop_opts = array()) {
			$prop_opts['_basepointImageBlock'] = 'exclude';
			return parent::print_r($retstr, $prop_opts);
		}
}

?>