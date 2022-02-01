<?php

function readline($prompt = null){
    if($prompt){
        echo $prompt;
    }
    $fp = fopen("php://stdin","r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
}

class imap_driver {

    private $sock;
    private $count = "00000001";
    public $response = "";
    public $last_endline = "";
    public $response_arr = array();
    public $num = 0;
    private $attach_id = "001";
    
    public function init($host, $port) {
        $this->sock = fsockopen($host, $port, $errno, $errstr, 15);
        if(!$this->sock) {
            echo "Connection to imap.gmail.com refused\n";
            return false;
        }
        $recv = fgets($this->sock);
        echo "Connected to imap.gmail.com\n";
        return true;
    }
    
    private function close() {
        fclose($this->sock);
    }
  
    private function command($command) {
        $this->response = "";
        $this->last_endline = "";
        
        fwrite($this->sock, "$this->count $command\r\n");
        
        while($line = fgets($this->sock)) {
            $this->response = $this->response.$line;
            $n = "";
            if(strpos($line, 'EXISTS') != false) {
                $n = str_replace('EXISTS', '', $line);
                $n = str_replace('*', '', $n);
                $line = trim($line);
                $this->num = (int)$n;
            }
            
            $line = trim($line);
            $line_arr = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            
            if(count($line_arr) > 0){
                $code = array_shift($line_arr);
                
                if(strtoupper($code) == $this->count) {
                    $this->last_endline = join(' ', $line_arr);
                    break;
                }
            } 
        }  
        $this->increment_counter();     
    }

    public function search($keyword) {
        $this->command("SEARCH $keyword");
        if(preg_match('~^OK~', $this->last_endline)) {
            return $this->response;
        }
        else {
            echo "Search failed\n";
            exit;
        }
    }

    public function search_general($keyword) {
        $res = $this->search($keyword);
        if(!$res){
            exit;
        }
        $res = str_replace('* SEARCH ','',$res);
        $res = str_replace($this->last_endline,'',$res);
        $counter = sprintf('%08d', intval($this->count) - 1);
        $res = str_replace($counter,'',$res);
        $res = trim($res);
        $numerical_id = "";
        $uids = array();
        $index = 0;
        $res = $res. " ";
        for($i = 0; $i < strlen($res); $i++) {
            if(ctype_digit($res[$i])){
                $numerical_id .= $res[$i];
            }
            else if(ctype_space($res[$i])){
                $uids[$index] = intval($numerical_id);
                $index++;
                $numerical_id = "";
            }
        }
        return $uids;
    }
    
    private function increment_counter() {
        $this->count = sprintf('%08d', intval($this->count) + 1);
    }
    
    public function login($login, $pwd) {
        $this->command("LOGIN $login $pwd");
        if(preg_match('~^OK~', $this->last_endline)) {
            echo "Successful login!";
            echo "\n";
            return true;
        }
        else {
            echo "Login failed\n";
            $this->close();
            return false;
        }
    }

    public function create($folder) {
        $this->command("CREATE $folder");
        if(preg_match('~^OK~', $this->last_endline)) {
            return true;
        }
        else {
            echo "Create failed\n";
            $this->close();
            return false;
        }
    }

    public function rename($folder_org, $folder_new) {
        $this->command("RENAME $folder_org $folder_new");
        if(preg_match('~^OK~', $this->last_endline)) {
            return true;
        }
        else {
            echo "Rename failed\n";
            $this->close();
            return false;
        }
    }
    
    public function delete($folder) {
        $this->command("DELETE $folder");
        if(preg_match('~^OK~', $this->last_endline)) {
            return true;
        }
        else {
            echo "Delete failed\n";
            $this->close();
            return false;
        }
    }
    
    public function select($folder) {
        $this->command("SELECT $folder");
        if(preg_match('~^OK~', $this->last_endline)) {
            echo $folder. " selected\n";
            return true;
        }
        else {
            echo "Select failed\n";
            $this->close();
            return false;
        }
    }
    
    public function logout() {
        $this->command('LOGOUT');
        if(preg_match('~^OK~', $this->last_endline)) {
            echo "Logged out!\n";
            echo "Bye...\n";
            return true;
        }
        else {
            echo "Logout failed";
            $this->close();
            return false;
        }
    }
    
    private function fetch($s){
        $this->response_arr = array();
        fwrite($this->sock, "$this->count $s\r\n");
        
        while($line = fgets($this->sock)) {
            $line = trim($line); 
            $this->response_arr[] = $line;
            $line_arr = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            
            if(count($line_arr) > 0){
                $code = array_shift($line_arr); 
                if(strtoupper($code) == $this->count) {
                    $this->last_endline = join(' ', $line_arr);
                    break;
                }
            }  
        }     
        array_pop($this->response_arr);
        $this->increment_counter();
    }

    public function get_specific_headers($uid) {
        $this->fetch("FETCH $uid BODY.PEEK[HEADER.FIELDS (from subject date)]");
        
        if (preg_match('~^OK~', $this->last_endline)) {
            array_shift($this->response_arr);                  
            $headers    = array();
            $prev_match = '';
            foreach ($this->response_arr as $item) {
                if (preg_match('~^([a-z][a-z0-9-_]+):~is', $item, $match)) {
                    $header_name           = strtolower($match[1]);
                    $prev_match            = $header_name;
                    $headers[$header_name] = trim(substr($item, strlen($header_name) + 1));  
                } 
                else {
                    $headers[$prev_match] .= " " . $item;
                }
            }
            return $headers; 
        } 
        else {
            echo "Fetch failed";
            $this->close();
            return false;
        }
    }
    
    public function extract_name($pass){
        $count = 0;
        $name = "";
        foreach ($this->response_arr as $item){ 
            $line = $item;
            if (strpos(strtolower(trim($line)), "filename=") or (substr($line, 0, 9) === "filename=")) {  
                $count += 1;
                if($count == $pass){
                    $pos = strpos($line, "filename=") + 9;
                    $name = trim($line);
                    if (strpos($line, " ", $pos) > 0) {
                        $name = substr($name, $pos, strpos($line, " ", $pos));
                    } 
                    else {
                        $name = substr($name, $pos);
                    }
                    $name = str_replace("\"", "", $name);
                }
            }
        }
        if (strpos($name, ".") == false){
            $name = $name.'.pdf';
        }
        return $name;
    }

    public function get_attachments($uid) {
        $attachments = array();
        $flag = 0;
        if (preg_match('~^OK~', $this->last_endline)) {   
            $index = 0;
            $names_arr = array();
            $headers    = array();
            $prev_match = "";
            $name = "";
            $id_no = "";
            $pass = 0;
            foreach ($this->response_arr as $item) {
                if (preg_match('~^([a-z][a-z0-9-_]+):~is', $item, $match)) {
                    $header_name = strtolower($match[1]);
                    if($header_name == 'x-attachment-id'){
                        $pass = $pass + 1;
                        if($pass > 1){
                            $pdf_decoded = base64_decode($headers[$id_no]);
                            $name = $this->extract_name($pass - 1);
                            file_put_contents($name,$pdf_decoded);
                            $names_arr[$index] = $name;
                            $index += 1;
                        }
                        $flag = 1;
                        $header_name = 'ID'.$this->attach_id;
                        $id_no = $header_name;
                        $this->attach_id = sprintf('%08d', intval($this->attach_id) + 1);
                    }
                }
                else if($flag == 1){
                   $headers[$id_no] .= " " . $item;
                }
            }
            if(empty($headers)){
                return $headers;
            }
            $pdf_decoded = base64_decode($headers[$id_no]); 
            $name = $this->extract_name($pass);
            file_put_contents($name,$pdf_decoded);
            $names_arr[$index] = $name;
            $index += 1;
        }
        return $names_arr;
    }

    public function get_content($uid) {
        $this->fetch("FETCH $uid BODY.PEEK[TEXT]");

        if (preg_match('~^OK~', $this->last_endline)) {
            $val = array_shift($this->response_arr);   
            $headers    = array();
            $code = array_shift($this->response_arr);
            $prev_match = "";
            $flag = 0;
            $flag1 = 0;
            foreach ($this->response_arr as $item) {
                if (preg_match('~^([a-z][a-z0-9-_]+):~is', $item, $match)) {
                    $header_name = strtolower($match[1]);
                    if($flag == 1){
                        break;
                    }
                    if($header_name == 'content-type'){
                        if(strpos($item, 'text/plain') != FALSE and $flag == 0) {
                            $headers[$header_name] = trim(substr($item, strlen($header_name)+1));
                            $prev_match = $header_name;
                            $flag = 1;
                        }
                    }
                } 
                else {
                    if($flag == 1 and !(substr($item, 0, 2) == '--')){
                        $headers[$prev_match] .= " " . $item;
                    }
                }
            }
            return $headers;
        }
        else {
            echo "Fetch failed";
            $this->close();
            return false;
        }
    }
    
    public function get_email($uid, $en) {
        $fetched_email = array();
        $headers = $this->get_specific_headers($uid);
        if($headers) {
            $fetched_email = array_merge($fetched_email, $headers);
        }
        else {
            return false;
        }
        
        $cont = $this->get_content($uid);
        if($cont) {
            $fetched_email = array_merge($fetched_email, $cont);
        }
        else {
            return false;
        }

        if($en) {
            $attach = $this->get_attachments($uid);
            if($attach) {
                $fetched_email = array_merge($fetched_email, $attach);
            }
            else {
                echo "\n";
            }
        }
        return $fetched_email;  
    }
    
    public function store_for_delete($uid){
        $this->response_arr = array();
        fwrite($this->sock, "$this->count STORE $uid +FLAGS (\DELETED)\r\n");
        
        while($line = fgets($this->sock)) {
            $line = trim($line); 
            $this->response_arr[] = $line;
            $line_arr = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            
            if(count($line_arr) > 0){
                $code = array_shift($line_arr);
                if(strtoupper($code) == $this->count) {
                    $this->last_endline = join(' ', $line_arr);
                    break;
                }
            }   
        }     
        array_pop($this->response_arr);
        $this->increment_counter();

        if(preg_match('~^OK~', $this->last_endline)) {
            echo "Store response ". $this->last_endline;
            echo "\n";
            return true;
        }

        return FALSE;   
    }
        
    public function expunge(){
        $this->command('EXPUNGE');
        if(preg_match('~^OK~', $this->last_endline)) {
            echo "Expunge response ". $this->last_endline;
            echo "\n";
            return true;
        }
        else {
            return FALSE;
        }
    } 
}

//75.101.100.43(new.toad.com)
class smtp_driver{
    
    private $sock;
    
    public function send_mail($sender_addr, $recv_addr) {
        
        $this->sock = fsockopen('75.101.100.43', 25, $errno, $errstr);
    
        if (!$this->sock) {
           echo "Connection to smtp server refused: ".$errstr;
           return false;
        }
        else {
            echo "Connected to new.toad.com smtp server\n";
        }    
        $recv = fgets($this->sock);

        fputs($this->sock, "MAIL FROM:<$sender_addr>\r\n");
        $recv = fgets($this->sock);
        if (!(strpos(trim($recv), "ok"))){
            echo "Error in sender email";
            exit;
        }

        foreach ($recv_addr as $r){
            fputs($this->sock, "RCPT TO:<$r>\r\n");
            $recv = fgets($this->sock);
            if (!(strpos(trim($recv), "ok"))){
                echo "Error in receiver email";
                exit;
            }
        }

        fputs($this->sock, "DATA\r\n");
        $recv = fgets($this->sock);
        if(!$recv){
            echo "Connection lost!";
            exit;
        }
        
        $message = "";

        $subject = readline("Subject: ");
        fputs($this->sock, "Subject:$subject\r\n");

        $date = date('Y-m-d H:i:s');
        fputs($this->sock, "Date:<$date>\r\n");
        
        $n_cc = readline("Number of people in cc: ");
        $i = 0;
        $CC = "";
        while($i < $n_cc){
            $cc = readline("CC: ");
            if($i){
                $CC = $CC.",".$cc;
            }
            else{
                $CC = $cc;
            }
            $i++;
        }
        fputs($this->sock, "cc:$CC\r\n");

        echo "Email content (end with a . on a line by itself): ";
        
        while(true){
            $msg = readline();
            if(strcmp($msg,".") == 0){
                break;
            }
            $message = $message.$msg."\n";
        }
        
        fputs($this->sock, "$message\r\n");
        fputs($this->sock, ".\r\n");
        $recv = fgets($this->sock);
        return true;
    }
}

?>

