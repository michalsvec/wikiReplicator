<?php
error_reporting(E_ERROR);



function pr($data){
	echo "<pre>";
	print_r($data);
	echo "</pre>";
}

class ReplicatorMaster extends SpecialPage {
	
	var $dir;	//actual directory
	var $cfg;	// master config
	var $log;	// log file
	var $xml;
	
	
	function __construct() {
		parent::__construct( 'ReplicatorMaster' );
		wfLoadExtensionMessages('ReplicatorMaster');
		
		$this->dir = dirname(__FILE__) . '/';
		$this->cfg = parse_ini_file($this->dir."master.ini",true);
		$this->log = fopen($this->dir."log/log.txt", "a+");
		$this->xml = fopen($this->dir."log/xml.txt", "w+");

	}

	function __destruct() {
		fclose($this->log);
		fclose($this->xml);
	}

	function logIt($str) {
		fwrite($this->log,date("Y-m-d H:i:s | ",mktime()).$str."\n");
	}



	/**
	 * Recursive upload of directories 
	 * file - dir, where to chdir on ftp
	 * path - local path
	 */
	function recursiveUploadDir($ftp,$path,$file) {
		
		// open local dir
		$dir = opendir($path); 

		if($dir){ 
			// if dir doesnt exists - create it
			if(!@ftp_chdir ($ftp, $file)) {
				ftp_mkdir ($ftp, $file);

				if(!ftp_chdir ($ftp, $file)) {
					$this->logIt("FTP: Unable to create directory $file");
					return;
				}
			}

			// Loop through each directory 
			while ($file = readdir($dir)) { 
				if($file == '.' || $file == '..')
					continue; 
				elseif(is_dir("$path/$file")){ 
					// Current file is a directory, so read content of the new directory 
					$this->recursiveUploadDir($ftp,"$path/$file",$file); 
				}
				else { 
					$size = ftp_size($ftp, $file);
					if($size != filesize("$path/$file")) {	// file doesnt exists or has different size
						if(!ftp_put($ftp, $file, "$path/$file" , FTP_BINARY))
							$this->logIt(__FUNCTION__."FTP: Unable to upload $path/$file");
					}
					else {
						$this->logIt("FTP: skip $file upload");
					}
				} 
			} 
			//echo "cd .. from ".ftp_pwd($ftp)."<br />";
			ftp_chdir ($ftp, "..");
			closedir($dir);
		}
		else {
			$this->logIt("Unable to open dir: ".$path);
		}
	}



	/**
	 * 	Connect to server and transfer data
	 * 
	 * 	$server - server variable from $this->cfg
	 */
	function putFtpFiles($server) {
		global $wgOut;
		
		// connect
		$ftp = ftp_connect($server["ftpserver"],21);
		if($ftp == FALSE) {
			$this->logIt("Unable to connect to ".$server["ftpserver"]); 
			$wgOut->addHtml("Unable to connect to ".$server["ftpserver"]);
			return;
		}
		else 
			$wgOut->addHtml("Connected to ".$server["ftpserver"]."<br />");

		// login
		if(!ftp_login($ftp, $server["ftpuser"], $server["ftppass"])) {
			$this->logIt("FTP login fail ".$server["ftpuser"]."@".$server["ftpserver"]); 
			$wgOut->addHtml("FTP login fail ".$server["ftpuser"]."@".$server["ftpserver"]);
			return;
		}
		else 
			$wgOut->addHtml("Login successfull as ".$server["ftpuser"]."@".$server["ftpserver"]."<br />");
		
		// enter wiki directory
		$imgdir = $server["wikidir"]."/images";
		if(!ftp_chdir($ftp, $imgdir)) {
			$this->logIt("FTP: Can not change dir from ".ftp_pwd($ftp)." to ".$imgdir); return;
		}
		else
			$wgOut->addHtml("Working dir ".ftp_pwd($ftp)."<br />");
		
		
		// recursive upload 
		// first iteration must be here
		$path = $this->dir."../../images";
		$wgOut->addHtml("path: ".$path."<br>");
		
		$dir = opendir($this->dir."../../images/");
		if($dir) { 
			// Loop through each directory 
			while ($file = readdir($dir)) { 
				if($file == '.' || $file == '..')
					continue; 
				elseif(is_dir("$path/$file")) { 
					// Current file is a directory, so read content of the new directory 
					$this->recursiveUploadDir($ftp,"$path/$file",$file); 
				}
				else {
					$size = ftp_size($ftp, $file);
					if($size != filesize("$path/$file")) {	// file doesnt exists or has different size
						if(!ftp_put($ftp, $file, "$path/$file" , FTP_BINARY))
							$this->logIt(__FUNCTION__."FTP: Unable to upload $path/$file");
					}
					else {
						$this->logIt("FTP: skip $file upload");
					}
				} 
			} 
			closedir($dir);
		}
		else 
			$this->logIt("Unable to open dir: ".$path);
		
		$wgOut->addHtml( "Upload done<br />");
		ftp_close($ftp);  
	}



	/* function from php.net comments */
	function get_curl($url)	{
		$curl = curl_init();

		// Setup headers - I used the same headers from Firefox version 2.0.0.6
		// below was split up because php.net said the line was too long. :/
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: "; // browsers keep this blank.

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com');
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$html = curl_exec($curl); // execute the curl command
		curl_close($curl); // close the connection

		return $html; // and finally, return $html
	}



	function dtbConnect($host, $user, $pass, $name) {

		$db = mysql_connect($host, $user, $pass);
		if(!$db) {
			$this->logIt("MySQL: Unable to connect to $host");
			return false;
		}
		else {
			$dbname = MySQL_Select_DB($name);
			if(!$dbname) {
				$this->logIt("MySQL: Unable to select database $name");
				return false;
			}
		}
		
		return $db;
	}



	/* from SpecialExport.php */
	function wfExportGetPagesFromCategory( $title ) {
		global $wgContLang;

		$name = $title->getDBkey();

		$dbr = wfGetDB( DB_SLAVE );

		list( $page, $categorylinks ) = $dbr->tableNamesN( 'page', 'categorylinks' );
		$sql = "SELECT page_namespace, page_title FROM $page " .
			"JOIN $categorylinks ON cl_from = page_id " .
			"WHERE cl_to = " . $dbr->addQuotes( $name );

		$pages = array();
		$res = $dbr->query( $sql, 'wfExportGetPagesFromCategory' );
		while ( $row = $dbr->fetchObject( $res ) ) {
			$n = $row->page_title;
			if ($row->page_namespace) {
				$ns = $wgContLang->getNsText( $row->page_namespace );
				$n = $ns . ':' . $n;
			}

			$pages[] = $n;
		}
		$dbr->freeResult($res);

		return $pages;
	}



	function agentCall() {
		global $wgOut;
		
		foreach($this->cfg["servers"] as $server) {
			if(!empty($this->cfg[$server]["title"]))
				$wgOut->addHtml("<h2>".$this->cfg[$server]["title"]."</h2>");
			
			$url = $this->cfg[$server]['url'].'index.php/Special:ReplicatorSlave/'.$server;
			//$url = $this->cfg[$server]["url"]."/repAgent.php?id=".$server;
			$wgOut->addHtml($url."<br>");
			
			// activate agent script	
			$agent = $this->get_curl($url);
			if(empty($agent)) $this->logIt("NULL response from $server");
			
			$this->putFtpFiles($this->cfg[$server]);
		}
	}


	/**
	 * Gets XML format from array representing table
	 */
	function getTableXml($name,$table) {
		$out = "<$name>\n";
		$j=0;
		foreach($table as $row) {
			$out .= "\t<row id='$j'>\n";
			foreach($row as $key=>$val) {
				// replace strings from cfg
				if($key == "old_text" || $key == "page_title" || $key == "pl_title" || $key == "el_to" || $key == "title" ||
					$key == "cl_sortkey") {
					$val = str_replace($this->cfg["what"],$this->cfg["with"], $val,$cnt);
				}
				$out .= "\t\t<$key>".base64_encode($val)."</$key>\n";
				//$out .= "\t\t<$key>".$val."</$key>\n";
			}
			$out .= "\t</row>\n";
			$j++;
		}
		$out .= "</$name>\n";	
		
		return $out;
		
	}
	

	/**
	 * Manual export of category, page, text and appropriate link tables
	 * 
	 * table Revision - main table connecting page and texts - selected only last revisions
	 * 
	 * TODO: export od pagelink table
	 */
	function generateXml() {
		global $wgOut;
		
		if(!$this->dtbConnect($this->cfg['database']['server'], 
				$this->cfg['database']['user'], 
				$this->cfg['database']['pass'], 
				$this->cfg['database']['name']))
		return;
		
		@mysql_query("SET CHARACTER SET utf8");

		@mysql_query("SET NAMES UTF8");

		@mysql_query("SET character_set_results=utf8");

		@mysql_query("SET character_set_connection=utf8");

		@mysql_query("SET character_set_client=utf8");
	
		$wgOut->disable();

		wfResetOutputBuffers();
		header( "Content-type: application/xml; charset=utf-8" );

		// arrays representing each table
		$tableCategory = array();
		$tableCategorylinks = array();
		$tableRevision = array();
		$tableImage = array();
		$tableImagelinks = array();
		$tableText = array();
		$tablePage = array();

		$pages = array();
		// get page ids from exported categories
		foreach($this->cfg['categories'] as $category) {
			// select all desired categories
			$sql = "SELECT * FROM `categorylinks` WHERE `cl_to` = '$category'";
			// now we have IDs of all pages from demanding categories
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				$pageIDs[] = $data['cl_from'];
				$tableCategorylinks[] = $data;
			}
		}
		
		// get latest revisions, imagelinks and ...
		$revisions = array();
		$images = array();
		foreach($pageIDs as $id) {
			$sql = "SELECT * FROM `revision` WHERE `rev_page` = '$id'";
			$query = mysql_query($sql);
			while($data = mysql_fetch_assoc($query)) {
				$revisions[$id] = $data['rev_id'];
			}
			
			$sql = "SELECT * FROM `imagelinks` WHERE `il_from` = '$id'";
			$query = mysql_query($sql);
			while($data = mysql_fetch_assoc($query)) {
				$tableImagelinks[] = $data;
				$images[] = $data['il_to'];
			}
		}

		// put revisions into xml array and save appropriate pages and texts
		foreach($revisions as $rev) {
			$sql = "SELECT * FROM `revision` WHERE `rev_id` = '$rev'";
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				// save one row in revision table
				$tableRevision[] = $data;

				// saves exported texts and pages
				$pages[] = $data['rev_page'];
				$texts[] = $data['rev_text_id'];
			}			
		}

		// get `page` table
		foreach($pages as $page) {
			$sql = "SELECT * FROM `page` WHERE `page_id` = '$page'";
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				$tablePage[] = $data;
			}
		}

		// get `text` table
		foreach($texts as $text) {
			$sql = "SELECT * FROM `text` WHERE `old_id` = '$text'";
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				$tableText[] = $data;
			}
		}
		
		// get `image` table
		foreach($images as $image) {
			$sql = "SELECT * FROM `image` WHERE `img_name` = '$image'";
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				$tableImage[] = $data;
			}
		}
		
		// get `category` table
		foreach($this->cfg['categories'] as $category) {
			$sql = "SELECT * FROM `category` WHERE `cat_title` = '$category'";
			$query = mysql_query($sql);
			
			while($data = mysql_fetch_assoc($query)) {
				$tableCategory[] = $data;
			}
		}
		

		$out  = $this->getTableXml("page",$tablePage);
		$out .= $this->getTableXml("revision",$tableRevision);
		$out .= $this->getTableXml("text",$tableText);
		$out .= $this->getTableXml("imagelinks",$tableImagelinks);
		$out .= $this->getTableXml("image",$tableImage);
		$out .= $this->getTableXml("category",$tableCategory);
		$out .= $this->getTableXml("categorylinks",$tableCategorylinks);
		
		$ret =  "<WikiReplicator>\n$out\n</WikiReplicator>";
		
		echo $ret;
		fwrite($this->xml,$ret);
		
		return;
	}



	function execute( $par ) {
		global $wgOut,$wgRequest;

		/**
		 * 	Replicator master can run in 3 modes
		 * 	1] agents executing	- empty $par
		 * 	2] generating wikiXML file, called by agents
		 * 	3] generating dataXML file, called by agents
		 * 
		 * 	differs in $par
		 */
		
		// @ -> Notice: undefined offset if $par is empty
		@list($id,$type) = split("/",$par);
		
		// calling agents
		if(empty($id)) {
			$this->agentCall();
		}
		// XML generation mode
		else {
			$this->logIt("Generating XML for $id");
			$this->generateXML($id);

		}
	}
}

?>
