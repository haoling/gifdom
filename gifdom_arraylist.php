<?php

class GifDOM_ArrayList extends ArrayList
{
	// ����
		protected $_autoChink = true;
	
}

class GifDOM_Blocks_copied extends GifDOM_ArrayList
{
	// ���
		// �Ѿ��������饹�Ǥ���������������뤳�Ȥǡ��������դ���������뤳�Ȥ�����롣
		const VALUE_TYPE = 'GifDOM_blk';
	
	// ����
		protected $_parent;
	
	// ���󥹥ȥ饯��
		public function __construct(GifDOM $parent) {
			parent::__construct();
			$this->_parent = $parent;
		}
	
	// �ѥ֥�å��᥽�å�
		/**
		 * ���ꤷ�������פΥ֥�å���������������
		 */
		public function GetChildrenByType($type) {
			$ret = new GifDOM_Blocks_copied($this->_parent);
			foreach($this as $i => $blk) {
				if($blk->GetType() == $type) {
					$ret[$i] = $blk;
				}
			}
			return $ret;
		}
		public function GetChildrenByExtensionType($type) {
			$ret = new GifDOM_Blocks_copied($this->_parent);
			foreach($this as $i => $blk) {
				if($blk->GetType() == GifDOM::BLK_EXTENSION && $blk->GetExtensionType() == $type) {
					$ret[$i] = $blk;
				}
			}
			return $ret;
		}
}

class GifDOM_Blocks extends GifDOM_Blocks_copied
{
	// �ѥ֥�å��᥽�å�
		public function set($key, $value) {
			if(method_exists($value, 'setparent')) {
				$value->SetParent($this->_parent);
			}
			return parent::set($key, $value);
		}
		public function insertBefore(GifDOM_blk $before, GifDOM_blk $value) {
			$key = $this->getKey($before, true);
			if($key === false) {
				throw new GifDOM_InvalidArgumentsException();
			}
			$index = $this->getIndex($key);
			if($index < 0) {
				throw new GifDOM_InvalidArgumentsException();
			}
			
			return $this->insertAt($index, $value);
		}
		public function insertAfter(GifDOM_blk $before, GifDOM_blk $value) {
			$key = $this->getKey($before, true);
			if($key === false) {
				throw new GifDOM_InvalidArgumentsException();
			}
			$index = $this->getIndex($key);
			if($index < 0) {
				throw new GifDOM_InvalidArgumentsException();
			}
			
			return $this->insertAt($index+1, $value);
		}
	
	// �����᥽�å�
		/**
		 * var_dump��print_r�����ݤˡ���󤹤�ץ�ѥƥ���̾����������֤���
		 */
		protected function __var_dump($ret) {
			$ret = parent::__var_dump($ret);
			if(($i = array_search('_parent', $ret)) !== false) unset($ret[$i]);
			return $ret;
		}
}

class GifDOM_ColorTable extends GifDOM_ArrayList
{
	// ���
		// �Ѿ��������饹�Ǥ���������������뤳�Ȥǡ��������դ���������뤳�Ȥ�����롣
		const VALUE_TYPE = 'GifDOM_pallet';
}
