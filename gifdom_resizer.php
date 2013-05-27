<?php

/*------------------------------------------

[Depends]

[Document]
画像をリサイズする
一度フレーム単位のGIFに分解して、GDでリサイズし、再合成する

[Example]
GIFを25x20に拡大・縮小する
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize(25, 20);
$resizer->resize();

幅が30〜40に収まるようにに拡大・縮小する
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(30, 0);
$resizer->TargetImageSizeMax(40, 0);
$resizer->resize();

GIFを25x20に縮小するが、小さい物は拡大しない
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(0, 0);
$resizer->TargetImageSizeMax(25, 20);
$resizer->resize();

GIFアニメを50%に縮小する
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize('50%', '50%');
$resizer->resize();

GIFを25x20に拡大・縮小し、枠を付けて30x30にする
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize(25, 20);
$resizer->addBorder(true);
$resizer->TargetFrameSize(30, '150%');
$resizer->resize();

GIFが50x50より大きい場合は50x50に縮小し、30x30より小さい場合は30x30に拡大して、余白を付けて60x60にする
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(30, 30);
$resizer->TargetImageSizeMax(50, 50);
$resizer->addBorder(true);
$resizer->TargetFrameSize(60, 60);
$resizer->resize();

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_resizer {
	// プロテクテッドメンバ
		protected $_gif;
		protected $_keepAspectRatio = true;
		protected $_addBorder = false;
		protected $_borderColor = 0x00FFFFFF;
		protected $_imageCopyMode = '';
		protected $_imageAlign = array('center', 'center');
		protected $_addWatermark = false;
		protected $_watermarkCallback = array(__CLASS__, '_watermarkCallbackExample');
		protected $_watermarkString = 'Sample';
		protected $_watermarkSize = array(40, 20);
		protected $_watermarkAlign = array('center', 'center');
		protected $_fwMin = 0, $_fhMin = 0, $_fwMax = 0, $_fhMax = 0;
		protected $_iwMin = 0, $_ihMin = 0, $_iwMax = 0, $_ihMax = 0;
	
	// コンストラクタ
		public function __construct(GifDOM_document $gif) {
			$this->_gif = $gif;
		}
	
	// 静的メソッド
		/**
		 * サイズ指定を解析する
		 * @param string $size 数値や50%などの文字列
		 * @param integer $base 基準となる数値
		 * @return float
		 */
		static public function parseSize($val, $base) {
			settype($val, 'string');
			$val = trim($val);
			
			if(preg_match('/^([0-9]+)%$/', $val, $mts)) {
				return $base * ($mts[1] / 100);
			}
			
			else {
				return (float)$val;
			}
		}
		/**
		 * 位置指定を解析する
		 * @param string $position 数値や、centerなどの文字列
		 * @param integer 外枠の長さ
		 * @param integer 描画物の長さ
		 * @return float
		 */
		static public function parsePosition($val, $frame, $obj) {
			settype($val, 'string');
			$val = trim($val);
			
			if(preg_match('/^([0-9]+)%$/', $val, $mts)) {
				return $frame * ($mts[1] / 100);
			}
			
			else if($val == 'left' || $val == 'top') {
				return 0;
			}
			else if($val == 'center' || $val == 'middle') {
				return ($frame - $obj) / 2;
			}
			else if($val == 'right' || $val == 'bottom') {
				return $frame - $obj;
			}
			
			else {
				return (float)$val;
			}
		}
		/**
		 * アスペクト比を保持して、指定した幅・高さに収まるようにフレームサイズを計算する
		 * 幅・高さにはどちらか片方0を指定できる
		 * このメソッドは浮動小数点数を返す。画像処理に使う場合はintにキャストして使用すること
		 */
		static public function calcKeepAspectRatioSize($tw, $th, $ow, $oh) {
			if($tw <= 0 && $th <= 0) return array($ow, $oh);
			
			if($tw <= 0) {
				$tw = ($th / $oh) * $ow;
			} else if($th <= 0) {
				$th = ($tw / $ow) * $oh;
			} else {
				$r = min($tw / $ow, $th / $oh);
				$tw = $ow * $r;
				$th = $oh * $r;
			}
			
			return array($tw, $th);
		}
		/**
		 * GifDOM_frameを元にImageCreateする
		 */
		static public function imageCreateFromGifDOM_frame(GifDOM_frame $frame, $basegd = null, $w = null, $h = null) {
			$imgblk = $frame->GetParent();
			if(is_null($w)) $w = $imgblk->GetWidth();
			if(is_null($h)) $h = $imgblk->GetHeight();
			if(is_null($basegd)) $basegd = ImageCreateFromString($frame->Save());
			
			$new = ImageCreate($w, $h);
			ImagePaletteCopy($new, $basegd);
			
			// 背景色で塗りつぶす
			$bgcolor = $frame->GetDocument()->GetBgColorIndex();
			ImageFill($new, 0, 0, $bgcolor);
			
			// 透過色で塗りつぶす
			$controls = $frame->GetBlocks()->GetChildrenByExtensionType(GifDOM_blk_Extension::BLK_GRAPHIC_CONTROL_EXTENSION);
			$trpcolor = null;
			if(count($controls)) {
				if($controls[0]->GetTransparentColorFlag()) {
					$trpcolor = $controls[0]->GetTransparentColorIndex();
					ImageFill($new, 0, 0, $trpcolor);
				}
			}
			
			return $new;
		}
	
	// パブリックメソッド
		/**
		 * リサイズ後のサイズを指定する
		 * 引数を片方でも省略すると現在の設定での計算結果を返す
		 * TargetFrameSizeMinとTargetFrameSizeMaxがこの値で上書きされる
		 * 0を指定するとアスペクト比を維持するよう計算する
		 * 余白を付けるか、トリムする場合のみ指定する。通常はTargetImageSizeメソッドを使用する
		 * @param int $width
		 * @param int $height
		 * @return array 計算結果
		 */
		public function TargetFrameSize($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				$this->TargetFrameSizeMin($val1, $val2);
				$this->TargetFrameSizeMax($val1, $val2);
			}
			
			if(! $this->_addBorder) {
				return $this->TargetImageSize();
			}
			
			list($ow, $oh) = $this->TargetImageSize();
			$wMin = $this->_fwMin;
			$hMin = $this->_fhMin;
			$wMax = $this->_fwMax;
			$hMax = $this->_fhMax;
			
			if($wMin <= 0 && $wMax <= 0) {
				$wMin = $wMax = $ow;
			} else if($wMin <= 0) {
				$wMin = min($wMax, $ow);
			} else {
				$wMax = max($wMin, $ow);
			}
			if($hMin <= 0 && $hMax <= 0) {
				$hMin = $hMax = $oh;
			} else if($hMin <= 0) {
				$hMin = min($hMax, $oh);
			} else {
				$hMax = max($hMin, $oh);
			}
			
			//df('ow: %d, oh: %d', $ow, $oh);
			//df('min: %d x %d, max: %d x %d', $wMin, $hMin, $wMax, $hMax);
			
			// アスペクト比を保持しない
			if($ow < $wMin) {
				$ow = $wMin;
			} else if($wMax < $ow) {
				$ow = $wMax;
			}
			if($oh < $hMin) {
				$oh = $hMin;
			} else if($hMax < $oh) {
				$oh = $hMax;
			}
			return array($ow, $oh);
		}
		/**
		 * リサイズ後の最小サイズを指定する
		 * 引数を片方でも省略すると現在の設定を返す
		 * 数値以外を指定した場合の基準値はリサイズ後の画像サイズ
		 * @param int $widthMin
		 * @param int $heightMin
		 * @return array 現在の設定
		 */
		public function TargetFrameSizeMin($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				list($ow, $oh) = $this->TargetImageSize();
				$val1 = static::parseSize($val1, $ow);
				$val2 = static::parseSize($val2, $oh);
				
				if($val1 < 0) $val1 = 0;
				if($val2 < 0) $val2 = 0;
				
				$this->_fwMin = $val1;
				$this->_fhMin = $val2;
				
				if($this->_fwMin > $this->_fwMax && $this->_fwMax > 0 && $this->_fwMin > 0) {
					$this->_fwMax = $this->_fwMin;
				}
				if($this->_fhMin > $this->_fhMax && $this->_fhMax > 0 && $this->_fhMin > 0) {
					$this->_fhMax = $this->_fhMin;
				}
			}
			
			return array($this->_fwMin, $this->_fhMin);
		}
		/**
		 * リサイズ後の最大サイズを指定する
		 * 引数を片方でも省略すると現在の設定を返す
		 * @param int $widthMax
		 * @param int $heightMax
		 * @return array 現在の設定
		 */
		public function TargetFrameSizeMax($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				list($ow, $oh) = $this->TargetImageSize();
				$val1 = static::parseSize($val1, $ow);
				$val2 = static::parseSize($val2, $oh);
				
				if($val1 < 0) $val1 = 0;
				if($val2 < 0) $val2 = 0;
				
				$this->_fwMax = $val1;
				$this->_fhMax = $val2;
				
				if($this->_fwMin > $this->_fwMax && $this->_fwMax > 0 && $this->_fwMin > 0) {
					$this->_fwMin = $this->_fwMax;
				}
				if($this->_fhMin > $this->_fhMax && $this->_fhMax > 0 && $this->_fhMin > 0) {
					$this->_fhMin = $this->_fhMax;
				}
			}
			
			return array($this->_fwMax, $this->_fhMax);
		}
		/**
		 * リサイズ後のサイズを指定する
		 * 画像はこの枠内に収まるようにリサイズされる
		 * Minより小さい画像は拡大され、Maxより大きい画像は縮小される
		 * 引数を片方でも省略すると現在の設定での計算結果を返す
		 * TargetImageSizeMinとTargetImageSizeMaxがこの値で上書きされる
		 * 0を指定するとアスペクト比を維持するよう計算する
		 * @param int $width
		 * @param int $height
		 * @return array 計算結果
		 */
		public function TargetImageSize($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				$this->TargetImageSizeMin($val1, $val2);
				$this->TargetImageSizeMax($val1, $val2);
			}
			
			if(! $this->_keepAspectRatio) {
				// アスペクト比を保持しない
				if(
					$this->_iwMin != $this->_iwMax ||	// 最小と最大が異なる
					$this->_ihMin != $this->_ihMax ||	// 最小と最大が異なる
					(
						// 両方ゼロでなく
						! ($this->_iwMin <= 0 && $this->_ihMin <= 0) &&
						(
							// 片方でも0以下のとき
							$this->_iwMin <= 0 ||
							$this->_ihMin <= 0
						)
					)
				) {
					throw new GifDOM_MissImplementationException('When without keep acpect ratio, you must call TargetFrameSize method with width and height parameter, must be both parameter has larger than 0.');
				}
				
				if($this->_iwMin <= 0 && $this->_ihMin <= 0) {
					return $this->OriginalImageSize();
				} else {
					return array($this->_iwMin, $this->_ihMin);
				}
			}
			
			list($ow, $oh) = $this->OriginalImageSize();
			$wMin = $this->_iwMin;
			$hMin = $this->_ihMin;
			$wMax = $this->_iwMax;
			$hMax = $this->_ihMax;
			
			list($wMin, $hMin) = static::calcKeepAspectRatioSize($wMin, $hMin, $ow, $oh);
			list($wMax, $hMax) = static::calcKeepAspectRatioSize($wMax, $hMax, $ow, $oh);
			
			//df('Min: %d x %d', $wMin, $hMin);
			//df('Max: %d x %d', $wMax, $hMax);
			
			if($ow < $wMin || $oh < $hMin) {
				// 幅・高さのどちらかがMin未満
				return array($wMin, $hMin);
			} else if($wMax < $ow || $hMax < $oh) {
				// 幅・高さのどちらかがMaxより大きい
				return array($wMax, $hMax);
			} else {
				return array($ow, $oh);
			}
		}
		/**
		 * リサイズ後の最小サイズを指定する
		 * 引数を片方でも省略すると現在の設定を返す
		 * @param int $widthMin
		 * @param int $heightMin
		 * @return array 現在の設定
		 */
		public function TargetImageSizeMin($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				list($ow, $oh) = $this->OriginalImageSize();
				$val1 = static::parseSize($val1, $ow);
				$val2 = static::parseSize($val2, $oh);
				
				if($val1 < 0) $val1 = 0;
				if($val2 < 0) $val2 = 0;
				
				$this->_iwMin = $val1;
				$this->_ihMin = $val2;
				
				if($this->_iwMin > $this->_iwMax && $this->_iwMax > 0 && $this->_iwMin > 0) {
					$this->_iwMax = $this->_iwMin;
				}
				if($this->_ihMin > $this->_ihMax && $this->_ihMax > 0 && $this->_ihMin > 0) {
					$this->_ihMax = $this->_ihMin;
				}
			}
			
			return array($this->_iwMin, $this->_ihMin);
		}
		/**
		 * リサイズ後の最大サイズを指定する
		 * 引数を片方でも省略すると現在の設定を返す
		 * @param int $widthMax
		 * @param int $heightMax
		 * @return array 現在の設定
		 */
		public function TargetImageSizeMax($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				list($ow, $oh) = $this->OriginalImageSize();
				$val1 = static::parseSize($val1, $ow);
				$val2 = static::parseSize($val2, $oh);
				
				if($val1 < 0) $val1 = 0;
				if($val2 < 0) $val2 = 0;
				
				$this->_iwMax = $val1;
				$this->_ihMax = $val2;
				
				if($this->_iwMin > $this->_iwMax && $this->_iwMax > 0 && $this->_iwMin > 0) {
					$this->_iwMin = $this->_iwMax;
				}
				if($this->_ihMin > $this->_ihMax && $this->_ihMax > 0 && $this->_ihMin > 0) {
					$this->_ihMin = $this->_ihMax;
				}
			}
			
			return array($this->_iwMax, $this->_ihMax);
		}
		/**
		 * 余白を付ける、トリムする際の画像の配置を指定する
		 * 数値の他に"center"などの文字列指定も出来る。
		 * なお、"center"と"50%"では意味が異なる。
		 * Frame幅30、画像幅40の場合、centerでは両側が5ずつ切り取られるが、50%では完成画像の左半分が空白となる。
		 * @param mixed $left 数値、もしくは位置指定文字列
		 * @param mixed $top 数値、もしくは位置指定文字列
		 * @return array 計算結果
		 */
		public function TargetImagePosition($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				$this->_imageAlign = array($val1, $val2);
			}
			
			list($iw, $ih) = $this->TargetImageSize();
			list($fw, $fh) = $this->TargetFrameSize();
			list($pl, $pt) = $this->_imageAlign;
			return array(static::parsePosition($pl, $fw, $iw), static::parsePosition($pt, $fh, $ih));
		}

		/**
		 * 元の画像サイズ
		 */
		public function OriginalImageSize() {
			return array($this->_gif->GetWidth(), $this->_gif->GetHeight());
		}
		/**
		 * 余白を含んだリサイズ先サイズ
		 */
		public function GetFrameSize() {
			if($this->_width <= 0 || $this->_height <= 0) return $this->GetTargetFrameSize();
			if($this->_addBorder) return $this->TargetSize();
			if(! $this->_keepAspectRatio) return $this->TargetSize();
			return $this->GetTargetFrameSize();
		}
		/**
		 * 余白を含まない画像のみのリサイズ先サイズ
		 */
		public function GetTargetFrameSize() {
			if($this->_width <= 0 && $this->_height <= 0) return $this->GetOriginalFrameSize();
			if(! $this->_keepAspectRatio) return array($this->_width, $this->_height);
			
			if($this->_width <= 0) {
				$r = $this->_height / $this->_gif->GetHeight();
				return array((int)($this->_gif->GetWidth() * $r), $this->_height);
			}
			else if($this->_height <= 0) {
				$r = $this->_width / $this->_gif->GetWidth();
				return array($this->_width, (int)($this->_gif->GetHeight() * $r));
			}
			else {
				$rw = $this->_width / $this->_gif->GetWidth();
				$rh = $this->_height / $this->_gif->GetHeight();
				$r = min($rw, $rh);
				return array((int)($this->_gif->GetWidth() * $r), (int)($this->_gif->GetHeight() * $r));
			}
		}
		/**
		 * アスペクト比を保持する
		 * 引数を省略すると現在の設定を返す
		 * 引数にはbooleanが指定できる
		 */
		public function keepAspectRatio($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_keepAspectRatio = $val;
			}
			
			return $this->_keepAspectRatio;
		}
		/**
		 * 余白を追加するか指定する
		 * 引数を省略すると現在の設定を返す
		 * 引数にはbooleanが指定できる
		 */
		public function addBorder($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_addBorder = $val;
			}
			
			return $this->_addBorder;
		}
		/**
		 * 透かしを追加するか指定する
		 * 引数を省略すると現在の設定を返す
		 * 引数にはbooleanが指定できる
		 */
		public function addWatermark($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_addWatermark = $val;
			}
			
			return $this->_addWatermark;
		}
		/**
		 * 透かしを追加する際のコールバック関数を登録する
		 * callback関数は以下の形式とすること
		 * callback関数内でパレットを変更してはならない
		 * void callback(resource $gdimage, $posX, $posY)
		 */
		public function wartermarkCallback($val = null) {
			$this->_watermarkCallback = $val;
		}
		/**
		 * 画像のコピー方式を指定する
		 * 引数を省略すると現在の設定を返す
		 * 引数には"resample","resize"が指定できる
		 */
		public function imageCopyMode($val = null) {
			if(! is_null($val)) {
				settype($val, 'string');
				$this->_imageCopyMode = $val;
			}
			
			return $this->_imageCopyMode;
		}
		/**
		 * リサイズする
		 */
		public function resize() {
			// 元サイズ
			list($ow, $oh) = $this->OriginalImageSize();
			//df('oSize: %d x %d', $ow, $oh);
			
			// 画像サイズ
			list($iw, $ih) = $this->TargetImageSize();
			//df('iSize: %d x %d', $iw, $ih);
			
			// フレームサイズ
			list($fw, $fh) = $this->TargetFrameSize();
			//df('fSize: %d x %d', $fw, $fh);
			
			// 描画位置
			list($pl, $pt) = $this->TargetImagePosition();
			//df('iPos: %d, %d', $pl, $pt);
			
			// 倍率を計算する
			$rw = $iw / $ow;
			$rh = $ih / $oh;
			//df('rw: %f, rh: %f', $rw, $rh);
			
			if($rw == 1.0 && $rh == 1.0 && ! $this->_addBorder) {
				return;
			}
			
			$origGifserialized = serialize($this->_gif);
			
			$this->_gif->GetBlocks()->startTransaction();
			
			try {
				for($i = 0; $i < $this->_gif->GetFrameCount(); $i++) {
					$orgframe = $this->_gif->GetFrame($i);
					$orgimgblk = $orgframe->GetParent();
					
					$oldframe = unserialize($origGifserialized)->GetFrame($i);
					$oldimgblk = $oldframe->GetParent();
					
					// 1フレーム分の画像サイズに切り出す
					$oldframe->GetDocument()->SetWidth($oldimgblk->GetWidth());
					$oldframe->GetDocument()->SetHeight($oldimgblk->GetHeight());
					$oldimgblk->SetLeftPosition(0);
					$oldimgblk->SetTopPosition(0);
					
					$gifgd = ImageCreateFromString($oldframe->Save());
					if(! $gifgd)
						throw new Exception('Cannot create gd image at frame '.$i);
					
					$new = static::imageCreateFromGifDOM_frame(
						$oldframe,
						$gifgd,
						$oldimgblk->GetWidth() * $rw,
						$oldimgblk->GetHeight() * $rh
					);
					
					switch($this->_imageCopyMode) {
					/*
					case 'resample':
						// TRUEカラー画像に一度コピーしてから再コピーする
						$new2 = ImageCreateTrueColor($oldimgblk->GetWidth(), $oldimgblk->GetHeight());
						ImageCopyMerge(
							$new2, $gifgd,
							0, 0, 
							0, 0, 
							$oldimgblk->GetWidth(),		// コピー元幅
							$oldimgblk->GetHeight(),	// コピー元高さ
							100
						);
						
						$new3 = ImageCreateTrueColor(ImageSX($new), ImageSY($new));
						// 透明色の定義
						extract(ImageColorsForIndex($new, $trpcolor-1));
						$color = ImageColorAllocateAlpha($new3, $red, $green, $blue, 255);
						ImageColorTransparent($new3, $color);
						
						//ImageAntiAlias($new3, true);
						ImageCopyResampled(
							$new3, $new2,
							0+($i==0?$posx:0), 0+($i==0?$posy:0), // コピー先座標
							0, 0, // コピー元座標
							$newframew,				// コピー先幅
							$newframeh,				// コピー先高さ
							$oldimgblk->GetWidth(),	// コピー元幅
							$oldimgblk->GetHeight()	// コピー元高さ
						);
						
						ImageCopy(
							$new, $new3,
							0, 0, // コピー先座標
							0, 0, // コピー元座標
							ImageSX($new3),	// コピー先幅
							ImageSY($new3)	// コピー先高さ
						);
						//if($i==1) { header('Content-Type: image/gif'); ImageGif($new3);exit; }
						ImageDestroy($new2);
						break;
					*/
					default:
						ImageCopyResized(
							$new, $gifgd,
							0, 0, // コピー先座標
							0, 0, // コピー元座標
							ImageSX($new),			// コピー先幅
							ImageSY($new),			// コピー先高さ
							$oldimgblk->GetWidth(),	// コピー元幅
							$oldimgblk->GetHeight()	// コピー元高さ
						);
					}
					
					$newpl = $pl;
					$newpt = $pt;
					if($pl != 0 || $pt != 0) {
						// 余白を付ける・トリムする
						if($i == 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, $fw, $fh);
							if((($this->_borderColor >> 24) & 0xFF) == 0) {
								// 背景色を塗る
								$color = ImageColorClosest(
									$new2,
									($this->_borderColor >> 16) & 0xFF,
									($this->_borderColor >>  8) & 0xFF,
									($this->_borderColor      ) & 0xFF
								);
								ImageFill($new2, 0, 0, $color);
							}
							ImageCopy(
								$new2, $new,
								$pl, $pt,		// コピー先座標
								0, 0,			// コピー元座標
								ImageSX($new),	// コピー元幅
								ImageSY($new)	// コピー元高さ
							);
							
							ImageDestroy($new);
							$new = $new2;
							$newpl = 0;
							$newpt = 0;
						}
						
						// 横を切る
						if($newpl < 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, ImageSX($new) + $newpl, ImageSY($new));
							ImageCopy(
								$new2, $new,
								0, 0,					// コピー先座標
								0-$newpl, 0,			// コピー元座標
								ImageSX($new)+$newpl,	// コピー元幅
								ImageSY($new)			// コピー元高さ
							);
							
							ImageDestroy($new);
							$new = $new2;
							$newpl = 0;
						}
						// 縦を切る
						if($newpt < 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, ImageSX($new), ImageSY($new) + $newpt);
							ImageCopy(
								$new2, $new,
								0, 0,					// コピー先座標
								0, 0-$newpt,			// コピー元座標
								ImageSX($new),			// コピー元幅
								ImageSY($new)+$newpt	// コピー元高さ
							);
							
							ImageDestroy($new);
							$new = $new2;
							$newpt = 0;
						}
					}
					
					ob_start();
					ImageGif($new);
					$newimg = ob_get_clean();
					ImageDestroy($gifgd);
					ImageDestroy($new);
					
					//if($i==0) { header('Content-Type: image/gif'); echo $newimg; exit; }
					
					// リサイズされたものを解析する
					$new = GifDOM::ParseGif($newimg);
					
					$newimgblk = $new->GetFrame(0)->GetParent();
					
					//printf('<textarea>%s</textarea>', $orgimgblk->GetDocument()->print_r(1));
					//printf('<textarea>%s</textarea>', $newimgblk->GetDocument()->print_r(1));
					//echo '<br />';
					
					// 位置を再計算する
					$orgimgblk->SetLeftPosition((int)(($orgimgblk->GetLeftPosition() * $rw) + $newpl));
					$orgimgblk->SetTopPosition((int)(($orgimgblk->GetTopPosition() * $rh) + $newpt));
					
					$orgimgblk->SetLZWMinimumCodeSide($newimgblk->GetLZWMinimumCodeSide());
					$orgimgblk->SetWidth($newimgblk->GetWidth());
					$orgimgblk->SetHeight($newimgblk->GetHeight());
					$orgimgblk->SetData($newimgblk->GetData());
					
					/*
					// 同じ種類のブロックを入れ替える
					$newblks = $new->GetFrame(0)->GetBlocks();
					foreach($newblks as $blk) {
						if($blk->GetType() == GifDOM::BLK_EXTENSION) {
							$olds = $frame->GetBlocks()->GetChildrenByExtensionType($blk->GetExtensionType());
							$news = $newblks->GetChildrenByExtensionType($blk->GetExtensionType());
						} else {
							$olds = $frame->GetBlocks()->GetChildrenByType($blk->GetType());
							$news = $newblks->GetChildrenByType($blk->GetType());
						}
						
						$pos = $news->GetIndex($news->GetKey($blk, true));
						if($pos >= count($olds)) {
							continue;
						}
						
						$old = $olds->GetAt($pos);
						$key = $this->_gif->GetBlocks()->GetKey($old);
						$this->_gif->GetBlocks()->Set($key, $blk);
					}
					*/
				}
				$this->_gif->GetBlocks()->commitTransaction();
				$this->_gif->SetWidth($fw);
				$this->_gif->SetHeight($fh);
			} catch(Exception $e) {
				$this->_gif->GetBlocks()->rollbackTransaction();
				throw $e;
			}
		}
	
	// 内部メソッド
}

