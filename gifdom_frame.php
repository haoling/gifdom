<?php

/*------------------------------------------

[Depends]

[Document]
GIFアニメーションの1フレームを仮想的に定義するクラス

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_frame extends GifDOM_document {
	// プロテクテッドメンバ
	
	// コンストラクタ
		protected function __construct(GifDOM_blk_Image $blk) {
			parent::__construct($blk);
		}
		public function __clone() {
			$newdoc = clone ($this->GetDocument());
			$this->SetParent($newdoc->GetFrame($this->GetFrameNumber())->GetParent());
		}
	
	// プロテクテッドメソッド
		protected function _SaveBin(GifDOM_BitStream $bs) {
			// 一時的にDocumentの_blocksを置き換えてセーブする
			
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
	
	// パブリックメソッド
		public function SetParent(GifDOM $parent) {
			if(! ($parent instanceof GifDOM_blk_Image)) {
				throw new GifDOM_InvalidArgumentsException();
			}
			parent::SetParent($parent);
		}
		/**
		 * このフレームの番号を取得する
		 * 削除されたフレームの場合は例外をスローする
		 */
		public function GetFrameNumber() {
			$imgs = $this->GetDocument()->GetBlocks()->GetChildrenByType(GifDOM::BLK_IMAGE);
			return $imgs->GetIndex($imgs->GetKey($this->GetParent(), true));
		}
		/**
		 * フレーム関連するブロックを取得する
		 * @param boolean $greed = false GIF全体に関係するブロックも含める
		 * @return GifDOM_Blocks_copied
		 */
		public function GetBlocks($greed = false) {
			$numFrame = $this->GetFrameNumber();
			$nowFrame = 0;
			$ret = new GifDOM_Blocks_copied($this->GetParent());
			foreach($this->GetDocument()->_blocks as $idx => $blk) {
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
		 * フレームを削除する
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