<?php
$url = 'http://stock.caijing.com.cn/20170427/4265222.shtml';
$iTextExtractor = new TextExtract( );
$article = $iTextExtractor->getPlainText($url);
if( $iTextExtractor->isGB ){	 $article = iconv( 'GBK', 'UTF-8//IGNORE', $article );	}
$imgArr = $iTextExtractor->imgHrefArr;
echo "article \n:$article\n";
var_dump($imgArr);
