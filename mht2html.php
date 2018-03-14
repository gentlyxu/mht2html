<?php
error_reporting(0);

if($_SERVER['argc'] < 2){
	echo 'mht2html v1.0.0, Render mhtml to html.', "\n\n";
	die("Usage: php {$_SERVER['argv'][0]} input_filename [output_filename]\n\n\n");
}

try{
	$_SERVER['argv'][2] = empty($_SERVER['argv'][2]) ? 'output.html' : $_SERVER['argv'][2];
	
	$m = new mht2html($_SERVER['argv'][1], $_SERVER['argv'][2]);
	$m->parse_mht();

	if($m->save_to_html() !== false){
		echo 'save html to ', $_SERVER['argv'][2]," OK!\n";
	}else{
		echo 'render html failed.', "\n";
	}
}catch(Exception $e){
	echo "Exception:", $e->getMessage(),"\n";
	die(0);
}

class mht2html{
	private $input_fcont = '';
	private $boundary_str = '';
	private $output_file = '';
	private $html_cont = '';
	public function __construct($from_file, $to_file = './output.html'){
		if(!is_file($from_file) || !is_readable($from_file)){
			throw new Exception('input file invalid!');
			return;
		}

		$this->input_fcont = file_get_contents($from_file);
		$this->output_file = $to_file;

		if(!is_dir(dirname($this->output_file))){
			mkdir(dirname($this->output_file), 0777, true);
		}
		$this->boundary_str = '';
	}

	private function get_boundary(){
		$arr_headers = explode("\n", substr($this->input_fcont, 0, 4096));
		$boundary_found =false;
		foreach($arr_headers as $header){
			if(preg_match('/boundary\s*=\s*[\'"]([0-9a-z\-_=\.]+?)["\']$/', $header, $matchs)){
				$boundary_found = true;
				$this->boundary_str = '--' . $matchs[1];
			}
		}

		if(!$boundary_found){
			throw new Exception('boundary string not matched.');
		}

	}

	public function save_to_html(){
		return file_put_contents($this->output_file, $this->html_cont);
	}

	public function parse_mht(){
		$this->get_boundary();

		$main_content = '';
		$arr_full_conts = explode($this->boundary_str, $this->input_fcont);
		array_shift($arr_full_conts); //destroy headers

		foreach($arr_full_conts as $part){

			if(strlen($part) < 10){
				continue;
			}

			$arr_parts = explode("\n", trim($part));
			$encoding = $type = $location = $cid = $disp = '';

			$part_cont_start = $curr_pos = 0;
			foreach($arr_parts as $line){
				$line = trim($line);
				if(empty($line)){
					$part_cont_start = $curr_pos;
					break;
				}elseif(strpos($line, ':') !== false){
					list($header_key, $header_val) = explode(':', $line);
					$header_key = strtolower($header_key);
					if(strcmp($header_key, 'content-transfer-encoding') == 0){
						$encoding = $header_val;
					}elseif(strcmp($header_key, 'content-type') == 0){
						$type = $header_val;
					}elseif(strcmp($header_key, 'content-location') == 0){
						$location = $header_val; 
					}elseif(strcmp($header_key, 'content-id') == 0){
						$cid = trim($header_val, "\r\n\t><");
					}elseif(strcmp($header_key, 'content-disposition') == 0){
						$disp = $header_val;
					}
				}
				$curr_pos += strlen($line) + 1;
			}

			if(empty($type)){
				continue;
			}

			list($type) = explode(';', $type);
			list($main_type, $sub_type) = explode('/', $type);
			$part_cont = substr($part, $part_cont_start);
			if(($type == 'html' || $type == 'text/html') && $encoding = 'base64'){
				$main_content = base64_decode($part_cont);
			}elseif($main_type == 'image'){
				if($disp == 'inline'){
					$main_content = str_replace("cid:{$cid}", "data:{$type};base64, {$part_cont}", $main_content);
				}else{//@todo : other disposition

				}
			}
		}
		$this->html_cont = $main_content;
		return $main_content;
	}
}