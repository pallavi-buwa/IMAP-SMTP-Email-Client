<?php

include('driver.php');

$imap_driver = new imap_driver();

echo '--------------------------------------------Imap Email Client--------------------------------------------'."\n";
echo 'Trying to connect to imap server....'."\n";

if($imap_driver->init('ssl://imap.gmail.com', 993) == false) {
    exit;
}

echo "\nLOGIN\n";
$e = readline('Email: ');
$p = readline('Password: ');
echo '---------------------------------------------------------------------------------------------------------'."\n\n";

if ($imap_driver->login($e, $p) == false) {
    exit;
}
while(true){
    echo "\n";
    echo '-------------------------------------'."\n";  
    echo '1. View emails'."\n";
    echo '2. Compose an email'."\n";
    echo '3. Delete an email'."\n";
    echo '4. Create a mailbox '."\n";
    echo '5. Delete a mailbox '."\n";
    echo '6. Rename a mailbox '."\n";
    echo '7. Search '."\n";
    echo '8. Log out '."\n";
    $choice = (int)readline("Choice: ");
    echo '-------------------------------------'."\n"; 

    if($choice >= 8 or $choice <= 0) {
        if ($imap_driver->logout() === false) {
            exit;
        }
        exit;
    }
    else if($choice == 1) {
        $folder = readline("Folder name: ");
        $en = readline("Enable download of attachments [1/0]:");
        if ($imap_driver->select($folder) === false) {
            exit;
        }
        else {
            $i = 1; 
            while(TRUE){
                $res = $imap_driver->get_email($i, $en);
                if($i > $imap_driver->num){
                    echo 'All emails fetched!'. "\n";
                    break;
                }
                else{
                    echo "-----------------------------------------------------------------------\n";
                    echo $i;
                    echo "\n";
                    print_r($res);
                    echo "-----------------------------------------------------------------------\n";
                }
                $i = $i + 1;
            }
        }
    }
    else if($choice == 2) {
        $smtp_driver = new smtp_driver();
        $sender = readline("Mail from: ");
        $num_recv = readline("Number of recipients: ");
        $receiver = array();
        $i = 1;
        while(true){
            if($i > $num_recv){
                break;
            }
            $rec = readline("Mail to: ");
            $receiver[$i] = $rec;
            $i = $i + 1;
        }
        
        $smtp_driver->send_mail($sender, $receiver);
        echo "\nMail sent!\n";

    }
    else if($choice == 3){
        $del = 1;
        $n = 0;
        $folder = readline("Folder name: ");
        if ($imap_driver->select($folder) === false) {
            exit;
        }
        else{
            $i = 1; 
            while(TRUE){
                $res = $imap_driver->get_specific_headers($i);
                if($i > $imap_driver->num){
                    echo 'All emails fetched!'. "\n";
                    break;
                }
                else{
                    echo "-----------------------------------------------------------------------\n";
                    echo $i;
                    echo "\n";
                    print_r($res);
                    echo "-----------------------------------------------------------------------\n";
                }
                $i = $i + 1;
            }
            
            $del = readline("ID of email to be deleted: ");
            if (($del1 = $imap_driver->store_for_delete($del)) == false) {
                echo "Store command failed\n";
                exit;
            }
            
            if($imap_driver->expunge() === FALSE){
                echo "Expunge command failed\n";
                exit;
            }
        }
    }
    else if($choice == 4){
        $folder = readline("Folder name: ");
        if ($imap_driver->create($folder) === false) {
            exit;
        }
        else{
            echo "Folder created successfully!\n";
        }
    }
    else if($choice == 5) {
        $folder = readline("Folder name: ");
        if ($imap_driver->delete($folder) === false) {
            exit;
        }
        else{
            echo "Folder deleted successfully!\n";
        }
    }
    else if($choice == 6){
        $folder1 = readline("Original name: ");
        $folder2 = readline("New name: ");
        if ($imap_driver->rename($folder1, $folder2) === false) {
            exit;
        }
        else{
            echo "Folder renamed successfully!\n";
        }
    }
    else if($choice == 7){
        echo "Search By \n";
        echo "1. Date\n";
        echo "2. Since\n";
        echo "3. Before\n";
        echo "4. From\n";
        echo "5. Subject\n";
        echo "6. Text\n";
        $ch = (int)readline("Choice: ");
        if($ch == 1){
            #Date: 01-nov-2021
            $date = readline("Date: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("ON \"$date\""); 
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else if($ch == 2){
            #Date: 01-nov-2021
            $date = readline("Date: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("SINCE \"$date\"");
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else if($ch == 3){
            #Date: 01-nov-2021
            $date = readline("Date: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("BEFORE \"$date\""); 
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else if($ch == 4){
            $rcpt = readline("From: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("FROM \"$rcpt\""); 
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else if($ch == 5){
            $sub = readline("Subject: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("SUBJECT \"$sub\"");
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else if($ch == 6){
            $txt = readline("Text: ");
            if(!$imap_driver->select('INBOX')){
                exit;
            }
            $res = $imap_driver->search_general("TEXT \"$txt\"");
            $res_t = $res;
            $check_null = (count(array_unique($res_t)) === 1);
            if($check_null and (count($res) != 1)){
                echo "No emails found!\n";
                continue;
            }
            for($i = 0; $i < count($res); $i++){
                $e = $imap_driver->get_email($res[$i], 0);
                echo "\n";
                print_r($e);
            }
        }
        else {
            echo "Try again!\n";
            continue;
        }
    }   
}

?>






