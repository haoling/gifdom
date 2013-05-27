<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

class GifDOM_BitStream extends HA_BitStream {
	// スタティックメンバ
		static $BYTEORDER = self::BYTEORDER_LITTLE;
}

