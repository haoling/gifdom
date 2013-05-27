<?php

/*------------------------------------------

[Depends]

[Document]
������ꥵ��������
���٥ե졼��ñ�̤�GIF��ʬ�򤷤ơ�GD�ǥꥵ���������ƹ�������

[Example]
GIF��25x20�˳��硦�̾�����
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize(25, 20);
$resizer->resize();

����30��40�˼��ޤ�褦�ˤ˳��硦�̾�����
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(30, 0);
$resizer->TargetImageSizeMax(40, 0);
$resizer->resize();

GIF��25x20�˽̾����뤬��������ʪ�ϳ��礷�ʤ�
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(0, 0);
$resizer->TargetImageSizeMax(25, 20);
$resizer->resize();

GIF���˥��50%�˽̾�����
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize('50%', '50%');
$resizer->resize();

GIF��25x20�˳��硦�̾������Ȥ��դ���30x30�ˤ���
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSize(25, 20);
$resizer->addBorder(true);
$resizer->TargetFrameSize(30, '150%');
$resizer->resize();

GIF��50x50����礭������50x50�˽̾�����30x30��꾮��������30x30�˳��礷�ơ�;����դ���60x60�ˤ���
$gif = \GifDOM::ParseGif($imgdata);
$resizer = new \GifDOM_resizer($gif);
$resizer->TargetImageSizeMin(30, 30);
$resizer->TargetImageSizeMax(50, 50);
$resizer->addBorder(true);
$resizer->TargetFrameSize(60, 60);
$resizer->resize();

------------------------------------------*/

// PHP5.1.0�ʾ�Ǽ¹�
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// �ե�����̾��åѡ�
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_resizer {
	// �ץ�ƥ��ƥåɥ���
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
	
	// ���󥹥ȥ饯��
		public function __construct(GifDOM_document $gif) {
			$this->_gif = $gif;
		}
	
	// ��Ū�᥽�å�
		/**
		 * �������������Ϥ���
		 * @param string $size ���ͤ�50%�ʤɤ�ʸ����
		 * @param integer $base ���Ȥʤ����
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
		 * ���ֻ������Ϥ���
		 * @param string $position ���ͤ䡢center�ʤɤ�ʸ����
		 * @param integer ���Ȥ�Ĺ��
		 * @param integer ����ʪ��Ĺ��
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
		 * �����ڥ�������ݻ����ơ����ꤷ�������⤵�˼��ޤ�褦�˥ե졼�ॵ������׻�����
		 * �����⤵�ˤϤɤ��餫����0�����Ǥ���
		 * ���Υ᥽�åɤ���ư�����������֤������������˻Ȥ�����int�˥��㥹�Ȥ��ƻ��Ѥ��뤳��
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
		 * GifDOM_frame�򸵤�ImageCreate����
		 */
		static public function imageCreateFromGifDOM_frame(GifDOM_frame $frame, $basegd = null, $w = null, $h = null) {
			$imgblk = $frame->GetParent();
			if(is_null($w)) $w = $imgblk->GetWidth();
			if(is_null($h)) $h = $imgblk->GetHeight();
			if(is_null($basegd)) $basegd = ImageCreateFromString($frame->Save());
			
			$new = ImageCreate($w, $h);
			ImagePaletteCopy($new, $basegd);
			
			// �طʿ����ɤ�Ĥ֤�
			$bgcolor = $frame->GetDocument()->GetBgColorIndex();
			ImageFill($new, 0, 0, $bgcolor);
			
			// Ʃ�ῧ���ɤ�Ĥ֤�
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
	
	// �ѥ֥�å��᥽�å�
		/**
		 * �ꥵ������Υ���������ꤹ��
		 * �����������Ǥ��ά����ȸ��ߤ�����Ǥη׻���̤��֤�
		 * TargetFrameSizeMin��TargetFrameSizeMax�������ͤǾ�񤭤����
		 * 0����ꤹ��ȥ����ڥ������ݻ�����褦�׻�����
		 * ;����դ��뤫���ȥ�ह����Τ߻��ꤹ�롣�̾��TargetImageSize�᥽�åɤ���Ѥ���
		 * @param int $width
		 * @param int $height
		 * @return array �׻����
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
			
			// �����ڥ�������ݻ����ʤ�
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
		 * �ꥵ������κǾ�����������ꤹ��
		 * �����������Ǥ��ά����ȸ��ߤ�������֤�
		 * ���Ͱʳ�����ꤷ�����δ���ͤϥꥵ������β���������
		 * @param int $widthMin
		 * @param int $heightMin
		 * @return array ���ߤ�����
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
		 * �ꥵ������κ��祵��������ꤹ��
		 * �����������Ǥ��ά����ȸ��ߤ�������֤�
		 * @param int $widthMax
		 * @param int $heightMax
		 * @return array ���ߤ�����
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
		 * �ꥵ������Υ���������ꤹ��
		 * �����Ϥ�������˼��ޤ�褦�˥ꥵ���������
		 * Min��꾮���������ϳ��礵�졢Max����礭�������Ͻ̾������
		 * �����������Ǥ��ά����ȸ��ߤ�����Ǥη׻���̤��֤�
		 * TargetImageSizeMin��TargetImageSizeMax�������ͤǾ�񤭤����
		 * 0����ꤹ��ȥ����ڥ������ݻ�����褦�׻�����
		 * @param int $width
		 * @param int $height
		 * @return array �׻����
		 */
		public function TargetImageSize($val1 = null, $val2 = null) {
			if(! is_null($val1) && ! is_null($val2)) {
				$this->TargetImageSizeMin($val1, $val2);
				$this->TargetImageSizeMax($val1, $val2);
			}
			
			if(! $this->_keepAspectRatio) {
				// �����ڥ�������ݻ����ʤ�
				if(
					$this->_iwMin != $this->_iwMax ||	// �Ǿ��Ⱥ��礬�ۤʤ�
					$this->_ihMin != $this->_ihMax ||	// �Ǿ��Ⱥ��礬�ۤʤ�
					(
						// ξ������Ǥʤ�
						! ($this->_iwMin <= 0 && $this->_ihMin <= 0) &&
						(
							// �����Ǥ�0�ʲ��ΤȤ�
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
				// �����⤵�Τɤ��餫��Min̤��
				return array($wMin, $hMin);
			} else if($wMax < $ow || $hMax < $oh) {
				// �����⤵�Τɤ��餫��Max����礭��
				return array($wMax, $hMax);
			} else {
				return array($ow, $oh);
			}
		}
		/**
		 * �ꥵ������κǾ�����������ꤹ��
		 * �����������Ǥ��ά����ȸ��ߤ�������֤�
		 * @param int $widthMin
		 * @param int $heightMin
		 * @return array ���ߤ�����
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
		 * �ꥵ������κ��祵��������ꤹ��
		 * �����������Ǥ��ά����ȸ��ߤ�������֤�
		 * @param int $widthMax
		 * @param int $heightMax
		 * @return array ���ߤ�����
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
		 * ;����դ��롢�ȥ�ह��ݤβ��������֤���ꤹ��
		 * ���ͤ�¾��"center"�ʤɤ�ʸ�����������롣
		 * �ʤ���"center"��"50%"�Ǥϰ�̣���ۤʤ롣
		 * Frame��30��������40�ξ�硢center�Ǥ�ξ¦��5�����ڤ����뤬��50%�Ǥϴ��������κ�Ⱦʬ������Ȥʤ롣
		 * @param mixed $left ���͡��⤷���ϰ��ֻ���ʸ����
		 * @param mixed $top ���͡��⤷���ϰ��ֻ���ʸ����
		 * @return array �׻����
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
		 * ���β���������
		 */
		public function OriginalImageSize() {
			return array($this->_gif->GetWidth(), $this->_gif->GetHeight());
		}
		/**
		 * ;���ޤ���ꥵ�����襵����
		 */
		public function GetFrameSize() {
			if($this->_width <= 0 || $this->_height <= 0) return $this->GetTargetFrameSize();
			if($this->_addBorder) return $this->TargetSize();
			if(! $this->_keepAspectRatio) return $this->TargetSize();
			return $this->GetTargetFrameSize();
		}
		/**
		 * ;���ޤޤʤ������ΤߤΥꥵ�����襵����
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
		 * �����ڥ�������ݻ�����
		 * �������ά����ȸ��ߤ�������֤�
		 * �����ˤ�boolean������Ǥ���
		 */
		public function keepAspectRatio($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_keepAspectRatio = $val;
			}
			
			return $this->_keepAspectRatio;
		}
		/**
		 * ;����ɲä��뤫���ꤹ��
		 * �������ά����ȸ��ߤ�������֤�
		 * �����ˤ�boolean������Ǥ���
		 */
		public function addBorder($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_addBorder = $val;
			}
			
			return $this->_addBorder;
		}
		/**
		 * Ʃ�������ɲä��뤫���ꤹ��
		 * �������ά����ȸ��ߤ�������֤�
		 * �����ˤ�boolean������Ǥ���
		 */
		public function addWatermark($val = null) {
			if(! is_null($val)) {
				settype($val, 'boolean');
				$this->_addWatermark = $val;
			}
			
			return $this->_addWatermark;
		}
		/**
		 * Ʃ�������ɲä���ݤΥ�����Хå��ؿ�����Ͽ����
		 * callback�ؿ��ϰʲ��η����Ȥ��뤳��
		 * callback�ؿ���ǥѥ�åȤ��ѹ����ƤϤʤ�ʤ�
		 * void callback(resource $gdimage, $posX, $posY)
		 */
		public function wartermarkCallback($val = null) {
			$this->_watermarkCallback = $val;
		}
		/**
		 * �����Υ��ԡ���������ꤹ��
		 * �������ά����ȸ��ߤ�������֤�
		 * �����ˤ�"resample","resize"������Ǥ���
		 */
		public function imageCopyMode($val = null) {
			if(! is_null($val)) {
				settype($val, 'string');
				$this->_imageCopyMode = $val;
			}
			
			return $this->_imageCopyMode;
		}
		/**
		 * �ꥵ��������
		 */
		public function resize() {
			// ��������
			list($ow, $oh) = $this->OriginalImageSize();
			//df('oSize: %d x %d', $ow, $oh);
			
			// ����������
			list($iw, $ih) = $this->TargetImageSize();
			//df('iSize: %d x %d', $iw, $ih);
			
			// �ե졼�ॵ����
			list($fw, $fh) = $this->TargetFrameSize();
			//df('fSize: %d x %d', $fw, $fh);
			
			// �������
			list($pl, $pt) = $this->TargetImagePosition();
			//df('iPos: %d, %d', $pl, $pt);
			
			// ��Ψ��׻�����
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
					
					// 1�ե졼��ʬ�β������������ڤ�Ф�
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
						// TRUE���顼�����˰��٥��ԡ����Ƥ���ƥ��ԡ�����
						$new2 = ImageCreateTrueColor($oldimgblk->GetWidth(), $oldimgblk->GetHeight());
						ImageCopyMerge(
							$new2, $gifgd,
							0, 0, 
							0, 0, 
							$oldimgblk->GetWidth(),		// ���ԡ�����
							$oldimgblk->GetHeight(),	// ���ԡ����⤵
							100
						);
						
						$new3 = ImageCreateTrueColor(ImageSX($new), ImageSY($new));
						// Ʃ���������
						extract(ImageColorsForIndex($new, $trpcolor-1));
						$color = ImageColorAllocateAlpha($new3, $red, $green, $blue, 255);
						ImageColorTransparent($new3, $color);
						
						//ImageAntiAlias($new3, true);
						ImageCopyResampled(
							$new3, $new2,
							0+($i==0?$posx:0), 0+($i==0?$posy:0), // ���ԡ����ɸ
							0, 0, // ���ԡ�����ɸ
							$newframew,				// ���ԡ�����
							$newframeh,				// ���ԡ���⤵
							$oldimgblk->GetWidth(),	// ���ԡ�����
							$oldimgblk->GetHeight()	// ���ԡ����⤵
						);
						
						ImageCopy(
							$new, $new3,
							0, 0, // ���ԡ����ɸ
							0, 0, // ���ԡ�����ɸ
							ImageSX($new3),	// ���ԡ�����
							ImageSY($new3)	// ���ԡ���⤵
						);
						//if($i==1) { header('Content-Type: image/gif'); ImageGif($new3);exit; }
						ImageDestroy($new2);
						break;
					*/
					default:
						ImageCopyResized(
							$new, $gifgd,
							0, 0, // ���ԡ����ɸ
							0, 0, // ���ԡ�����ɸ
							ImageSX($new),			// ���ԡ�����
							ImageSY($new),			// ���ԡ���⤵
							$oldimgblk->GetWidth(),	// ���ԡ�����
							$oldimgblk->GetHeight()	// ���ԡ����⤵
						);
					}
					
					$newpl = $pl;
					$newpt = $pt;
					if($pl != 0 || $pt != 0) {
						// ;����դ��롦�ȥ�ह��
						if($i == 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, $fw, $fh);
							if((($this->_borderColor >> 24) & 0xFF) == 0) {
								// �طʿ����ɤ�
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
								$pl, $pt,		// ���ԡ����ɸ
								0, 0,			// ���ԡ�����ɸ
								ImageSX($new),	// ���ԡ�����
								ImageSY($new)	// ���ԡ����⤵
							);
							
							ImageDestroy($new);
							$new = $new2;
							$newpl = 0;
							$newpt = 0;
						}
						
						// �����ڤ�
						if($newpl < 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, ImageSX($new) + $newpl, ImageSY($new));
							ImageCopy(
								$new2, $new,
								0, 0,					// ���ԡ����ɸ
								0-$newpl, 0,			// ���ԡ�����ɸ
								ImageSX($new)+$newpl,	// ���ԡ�����
								ImageSY($new)			// ���ԡ����⤵
							);
							
							ImageDestroy($new);
							$new = $new2;
							$newpl = 0;
						}
						// �Ĥ��ڤ�
						if($newpt < 0) {
							$new2 = static::imageCreateFromGifDOM_frame($oldframe, $gifgd, ImageSX($new), ImageSY($new) + $newpt);
							ImageCopy(
								$new2, $new,
								0, 0,					// ���ԡ����ɸ
								0, 0-$newpt,			// ���ԡ�����ɸ
								ImageSX($new),			// ���ԡ�����
								ImageSY($new)+$newpt	// ���ԡ����⤵
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
					
					// �ꥵ�������줿��Τ���Ϥ���
					$new = GifDOM::ParseGif($newimg);
					
					$newimgblk = $new->GetFrame(0)->GetParent();
					
					//printf('<textarea>%s</textarea>', $orgimgblk->GetDocument()->print_r(1));
					//printf('<textarea>%s</textarea>', $newimgblk->GetDocument()->print_r(1));
					//echo '<br />';
					
					// ���֤�Ʒ׻�����
					$orgimgblk->SetLeftPosition((int)(($orgimgblk->GetLeftPosition() * $rw) + $newpl));
					$orgimgblk->SetTopPosition((int)(($orgimgblk->GetTopPosition() * $rh) + $newpt));
					
					$orgimgblk->SetLZWMinimumCodeSide($newimgblk->GetLZWMinimumCodeSide());
					$orgimgblk->SetWidth($newimgblk->GetWidth());
					$orgimgblk->SetHeight($newimgblk->GetHeight());
					$orgimgblk->SetData($newimgblk->GetData());
					
					/*
					// Ʊ������Υ֥�å��������ؤ���
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
	
	// �����᥽�å�
}

