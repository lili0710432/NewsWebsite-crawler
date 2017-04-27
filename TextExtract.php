<?php
/**
 * @name TextExtract
 * @desc 行列分布的网页集中文本抓取类 
 * textExtract - text extraction class
 * Created on 2016-08-10
 * author: hello  world
 */
ini_set('memory_limit','1000M');
ini_set('pcre.backtrack_limit', 99999);
class TextExtract{
	const RATE =  0.8;					//文本快长度 0.8倍之内的认为都是正文
	const MIN_LEN_BLOCK =  10;			//块的最短长度
	public $url         = '';			//the web page's url
	public $rawPageCode = '';			//the web page's source code
	public $textLines   = array();		//the text after preprocessing
	public $imgArr	    = array();		//the {img} after preprocessing
	public $blksLen     = array();		//the length of each block
	public $linesLen    = array();		//the length of each  line
	public $text        = '';			//the final extracted text
	public $imgHrefArr	= array();		//the final extracted img
	public $blkSize;					//the size of each block ( regards how many single lines as a block )
	public $isGB;						//whether the web page's encoding is 'gb*'
	public $optArr = array( 
		'http'=>array( 		'method'=>"GET", 	'timeout'=>5, 	'ignore_errors'=>true,	) 
	);
	public $strm;						//create socket data stream 
	function __construct($_blkSize = 4 ) {
		$this->blkSize = $_blkSize;
		$this->strm=stream_context_create($this->optArr);
	}
	/**
	 * Get the web page's source code
	 * @return void
	 */
	function getPageCode() {
		$this->rawPageCode = file_get_contents( $this->url,0,$this->strm);
	}
	/**
	 * Transform the web page's source code according to its encoding,
	 * and set the value of member $isGB for correctly display
	 * @return void
	 */
	function procEncoding() {
		//$pattern = '/<meta charset(\s*?)=(\s*?)(.*?)">/i';
		$pattern = "/<meta.*?charset\s*=\s*(.*?)>/i";
		preg_match( $pattern, $this->rawPageCode, $matches );
		if(!empty($matches[1])){
			$tmp = strtoupper($matches[1]);
			if( strpos($tmp,'GB') === false ) {
				$this->isGB = false;
				//$replacement = 'charset=GBK"';
				$replacement = '';
				$this->rawPageCode = preg_replace( $pattern, $replacement, $this->rawPageCode );
			} else {
				$this->isGB = true;
			}
		}else{
			$pattern = "/<meta.*?utf-8.*?>/i";
			preg_match( $pattern, $this->rawPageCode, $matches );
			if(!empty($matches[0])){
				$this->isGB = false;
			}else{
				$pattern = "/<head[\w\W]*?utf-8[\w\W]*?<\/head>/i";
				preg_match( $pattern, $this->rawPageCode, $matches );
				if(!empty($matches[0])){
					$this->isGB = false;
				}else{
					$this->isGB = true;
				}
			}	
		}
	}
	/**
	 * Preprocess the web page's source code
	 * @return string
	 */
	function preProcess() {
		$content = $this->rawPageCode;
		// only handle with body section
		$start=strpos($content ,'<body');
   	 	$end=strpos($content ,'</body');
    	        $content=substr($content,$start,$end-$start);
		// 1. DTD information
		$pattern = '/<!DOCTYPE.*?>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		// 2. HTML comment
		$pattern = '/<!--.*?-->/s';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		// 3. Java Script
		$pattern = '/<script.*?>.*?<\/script>/si';	
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		// 4. CSS
		$pattern = '/<style.*?>.*?<\/style>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		// 5. href 
		$replacement = '';
		$content = str_replace( '<article>', $replacement, $content );
		$content = str_replace( '</article>', $replacement, $content );
		/*
		$pattern = '/<a.*?>.*?<\/a>/si';//因为里面可能有图片
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		$pattern = '/<strong.*?>.*?<\/strong>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		*/
		
		$pattern = '/<span.*?>.*?<\/span>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		$pattern = '/<font.*?>.*?<\/font>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );	

		$pattern = '/<h.*?>.*?<\/h.*?>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );	
		$pattern = '/<ul.*?>.*?<\/ul>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );	
		$pattern = '/<nobr>.*?<\/nobr>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		$pattern = '/<em.*?>.*?<\/em>/si';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
				
		
		$regex = "/<img.*?src\s*?=\s*?\"(.*?)\".*?>/si";
		$out = array();
		$ret = preg_match_all($regex, $content, $out);
		$picNum = count($out[0]);
		if( $picNum > 0 ) {
			$tmpReplace=array();
			for($i = 0; $i < $picNum; $i++) {
				array_push($tmpReplace, '{'.(trim($out[1][$i])).'}');
			}
			$content = str_replace($out[0],$tmpReplace,$content);
		}		
		
		// 5. HTML TAGs
		$pattern = '/<.*?>/s';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		// 6. some special charcaters
		$pattern = '/&.{1,6};|&#.{1,6};/';
		$replacement = '';
		$content = preg_replace( $pattern, $replacement, $content );
		
		$content = str_replace('&nbsp',' ',$content);
		return $content;
	}	
	/**
	 * Split the preprocessed text into lines by '\n'
	 * after replacing "\r\n", '\n', and '\r' with '\n'
	 * @param string @rawText
	 * @return void
	 */
	function getTextLines( $rawText ) {
		// do some replacement
		$order = array( "\r\n", "\n", "\r" );
		$replace = '\n';
		$rawText = str_replace( $order, $replace, $rawText );
		$lines = explode( '\n', $rawText );
		$this->textLines=array();
		$this->linesLen=array();
		$this->imgArr=array();
		$index=0;
		foreach( $lines as $line ) {
			// remove the blanks in each line
			$tmp = preg_replace( '/\s+/s', '', $line );
			$pattern="/{(.*?)}/si";
			// restore  img  into  imgArr
			if( (strpos($tmp,'{http')!== false)  ){
				$out=array();
				$ret = preg_match_all($pattern, $tmp, $out);
				if(!empty($out[1][0])){			
					$this->imgArr[$index]=$out[1];
				}
				$tmp = preg_replace( $pattern, '', $tmp );
			}else if ( strpos($tmp,'{/')!== false ) { //  eg:  /uri/**.jpg
				$tmp = preg_replace( $pattern, '', $tmp );
			}
			$this->textLines[$index] = $tmp;
			$this->linesLen [$index] = strlen($tmp);
			$index++;
		}
	}
	/**
	 * Calculate the blocks' length
	 * @return void
	 */
	function calBlocksLen() {
		$textLineNum = count( $this->textLines );
		$this->blksLen=array();
		// calculate the first block's length
		$blkLen = 0;
		for( $i = 0; $i < $this->blkSize; $i++ ) {
			//$blkLen += strlen( $this->textLines[$i] );
			$blkLen += ( $this->linesLen[$i] );
		}
		$this->blksLen[] = $blkLen;
		// calculate the other block's length using Dynamic Programming method
		for( $i = 1; $i <= ($textLineNum - $this->blkSize); $i++ ) {	//rewrite here, '$i<'  =>  '$i<='
			//$blkLen = $this->blksLen[$i - 1] + strlen( $this->textLines[$i - 1 + $this->blkSize] ) - strlen( $this->textLines[$i - 1] );
			$blkLen = $this->blksLen[$i - 1] + ( $this->linesLen[$i - 1 + $this->blkSize] ) - ( $this->linesLen[$i - 1] );
			$this->blksLen[] = $blkLen;
		}
	}
	/**
	 * Extract the text from the web page's source code
	 * according to the simple idea:
	 * [the text should be the longgest continuous content in the web page]
	 * @return string
	 */
	function getPlainText($_url) {
		if(empty($_url)){	return '';	}
		$this->url = $_url;
		$this->getPageCode();
		if(empty($this->rawPageCode)){	return '';	}
		$this->procEncoding();
		$preProcText = $this->preProcess();
		$this->getTextLines( $preProcText );
		$this->calBlocksLen();
		$i = $avgLen = $maxTextLen = 0;
		$blkNum = count( $this->blksLen );
		$strArr=array();
		$lenArr=array();
		$imgArr=array();
		$totalIndex=0;
		while( $i < $blkNum ) {
			while( ($i < $blkNum) && ($this->blksLen[$i] == 0) ) {	$i++;	}
			if( $i >= $blkNum )			{ 	break;	}
			$c= $curTextLen = $curAvgLen=0;
			$portion = '';
			$picArr=array();
			while( ($i < $blkNum) && ($this->blksLen[$i] != 0) ) {
				if( $this->textLines[$i] != '' ) {
					$portion .= $this->textLines[$i];
					$portion .= '<br />';
					//$curTextLen += strlen( $this->textLines[$i] );
					$curTextLen += ( $this->linesLen[$i] );
					$c++;
				}
				if(!empty($this->imgArr[$i])){
					foreach($this->imgArr[$i] as  $picItem){
						$picArr[]=$picItem;
					}
				}
				$i++;
			}
			//To avoid the picture in the final situation
			if(!empty($this->imgArr[$i])){
				foreach($this->imgArr[$i] as  $picItem){
					$picArr[]=$picItem;
				}
			}
			if($c>0){	$curAvgLen=$curTextLen/$c;	}
			if( $curTextLen > $maxTextLen &&  $curAvgLen>$avgLen ) {
				$maxTextLen = $curTextLen;
				$avgLen=$curAvgLen;
			}
			if($curTextLen>(self::MIN_LEN_BLOCK) && $curTextLen>=((self::RATE)*$maxTextLen) ){
				$strArr[$totalIndex]=$portion;
				$lenArr[$totalIndex]=$curTextLen;
				$imgArr[$totalIndex]=$picArr;
				$totalIndex++;
			}
		}
		$c=count($lenArr);
		$this->text ='';
		$this->imgHrefArr=array();
		for($i=0;$i<$c;$i++){
			if( $lenArr[$i]>=((self::RATE)*$maxTextLen)){
				$this->text=($this->text).($strArr[$i]);
				if(!empty($imgArr[$i])){
					foreach($imgArr[$i] as $picItem){
						$this->imgHrefArr[]=$picItem;
					}
				}
			}
		}
		unset($strArr);
		unset($lenArr);
		unset($imgArr);
		return $this->text;
	}
	public function __destruct(  ) {
		$this->url = '';
		unset($this->text);
		unset($this->rawPageCode);
		unset($this->textLines);
		unset($this->linesLen);
		unset($this->blksLen);
		unset($this->imgArr);
		unset($this->imgHrefArr);
	}
}
