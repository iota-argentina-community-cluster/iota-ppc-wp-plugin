<?php

/* 
* Set the iota public node to http or https depending on site
* Can manually overide this value: just set $url to whatever node you want
*/

if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
    // use a https node server if serving php on a https site
    $url = "https://nodes.iota.cafe:443";
} else {
    // use http node - https not detected
    $url = "http://eugene.iota.community:14265";
}

/* Function to get the transaction hash for a specific address
*  Takes an address and node url as input
*  Returns either "ERROR" or transaction hash
*/

function getTransactionHash($inputAddress,$url) {

    $data = array("command" => "findTransactions", "addresses" => $inputAddress);                                                                    
    $data_string = json_encode($data);                                                                                   
                                                                                                                         
    $ch = curl_init($url);                                                                      
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json',
        'X-IOTA-API-Version: 1.4.2.1',
        'Content-Length: ' . strlen($data_string))                                                                       
    );                                                                                                                   
    
    $result = curl_exec($ch);                                                                                                                                            

    // check node response is ok (returns "ERROR" string if bad request)
    if (!curl_errno($ch)) {
        switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
          case 200:
            // ok - do nothing      
            break;
          default:            
          $result = "ERROR";
        }
    } else {
          $result = "ERROR";
    }

    curl_close($ch);

    return $result;

}

/* Function to get the trytes for a transaction hash
*  Takes a hash and node url as input
*  Returns either "ERROR" or tryte array
*/

function getTrytes($inputHash,$url) {
    
    $data = array("command" => "getTrytes", "hashes" => $inputHash);                                                                    
    $data_string = json_encode($data);                                                                                   
                                                                                                                            
    $ch = curl_init($url);                                                                      
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json',
        'X-IOTA-API-Version: 1.4.2.1',
        'Content-Length: ' . strlen($data_string))                                                                       
    );                                                                                                                   
    
    $result = curl_exec($ch);                                                                                                                                            

    // check node response is ok (returns "ERROR" string if bad request)
    if (!curl_errno($ch)) {
        switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            case 200:
            // ok - do nothing      
            break;
            default:            
            $result = "ERROR";
        }
    } else {
            $result = "ERROR";
    }

    curl_close($ch);

    return $result;

}
    
/* Function to transform trytes to transaction data (address, value)
*  Takes a single tryte as input
*  Returns either "ERROR" or an array with address, value, etc.
*/

function getDataFromTrytes($trytes) {
    
    // validate trytes
    for ($i = 2279; $i < 2295; $i++) {
        
        if ($trytes[$i] !== "9") {
            return "ERROR";
        }
    }

    $txAddress = substr($trytes,2187,81);
    $signatureMessageFragment = substr($trytes,0, 2187);    
    $tag = substr($trytes,2592,27);

    // validate items exist
    if ((!isset($txAddress)) || (!isset($signatureMessageFragment)) || (!isset($tag))) {
        return "ERROR";
    }            

    // get the spend for that particular address - could be pending
    $transactionTrits = trits($trytes);
    $spend = value(array_slice($transactionTrits,6804,33));

    // validate spend value
    if (!isset($spend)) {
        return "ERROR";
    }

    // set the return items as an array
    $outputArray['txAddress'] = $txAddress;
    $outputArray['signatureMessageFragment'] = $signatureMessageFragment;
    $outputArray['tag'] = $tag;
    $outputArray['value'] = $spend;

    return $outputArray;

}    

// function to get confirmed balance for an address
function getBalances($inputAddress,$url) {
    
    $data = array("command" => "getBalances", "addresses" => $inputAddress, "threshold" => 100);                                                                    
    $data_string = json_encode($data);                                                                                   
                                                                                                                            
    $ch = curl_init($url);                                                                      
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json',
        'X-IOTA-API-Version: 1.4.2.1',
        'Content-Length: ' . strlen($data_string))                                                                       
    );                                                                                                                   
    
    $result = curl_exec($ch);                                                                                                                                            

    // check node response is ok (returns "ERROR" string if bad request)
    if (!curl_errno($ch)) {
        switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            case 200:
                // ok - get balance
                $resultJson = (array) json_decode($result);                                                                                                   
                $balance = $resultJson['balances'];                
                $result = $balance[0];                
                break;
            default:            
                $result = "ERROR";
        }
    } else {
            $result = "ERROR";
    }

    curl_close($ch);

    return $result;

}

// helper function for getDataFromTrytes
function trits($input) {
    
    $trits = [];

    // All possible tryte values
    $trytesAlphabet = "9ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    // map of all trits representations
    $trytesTrits = [
    [ 0,  0,  0],
    [ 1,  0,  0],
    [-1,  1,  0],
    [ 0,  1,  0],
    [ 1,  1,  0],
    [-1, -1,  1],
    [ 0, -1,  1],
    [ 1, -1,  1],
    [-1,  0,  1],
    [ 0,  0,  1],
    [ 1,  0,  1],
    [-1,  1,  1],
    [ 0,  1,  1],
    [ 1,  1,  1],
    [-1, -1, -1],
    [ 0, -1, -1],
    [ 1, -1, -1],
    [-1,  0, -1],
    [ 0,  0, -1],
    [ 1,  0, -1],
    [-1,  1, -1],
    [ 0,  1, -1],
    [ 1,  1, -1],
    [-1, -1,  0],
    [ 0, -1,  0],
    [ 1, -1,  0],
    [-1,  0,  0]
    ];

    // check if tryte is number or string
    if (is_numeric($input)) {

        $absoluteValue = $input;

        if ($input < 0) {
                $absoluteValue = -$input;
        }

        while ($absoluteValue > 0) {

            $remainder = $absoluteValue % 3;
            $absoluteValue = floor($absoluteValue / 3);

            if ($remainder > 1) {
                $remainder = -1;
                $absoluteValue++;
            }

            $tritLength = count($trits);
            $trits[$tritLength] = $remainder;
        }
        if ($input < 0) {

            for ($i = 0; $i < count($trits); $i++) {

                $trits[$i] = -$trits[$i];
            }
        }
    } else {

        for ($i = 0; $i < strlen($input); $i++) {

            $inputVal = $input[$i];
            $index = strpos($trytesAlphabet,$inputVal);

            $trits[$i * 3] = $trytesTrits[$index][0];
            $trits[$i * 3 + 1] = $trytesTrits[$index][1];
            $trits[$i * 3 + 2] = $trytesTrits[$index][2];
        }
    }

    return $trits;
}

// helper function for getDataFromTrytes
function value($trits) {

    $returnValue = 0;
    for ( $i = count($trits); $i-- > 0; ) {
        $returnValue = $returnValue * 3 + $trits[ $i ];
    }

    return $returnValue;
}

?>
