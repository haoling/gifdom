<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0�ʾ�Ǽ¹�
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// �ե�����̾��åѡ�
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_BitStream extends HA_BitStream {
	// �����ƥ��å�����
		static $BYTEORDER = self::BYTEORDER_LITTLE;
}

