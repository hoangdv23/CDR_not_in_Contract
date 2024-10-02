<?php
require_once __DIR__ . '/vendor/autoload.php';
include_once '/root/digitelTool/databases/sv136.php';
include_once '/root/digitelTool/databases/billing140.php';
use Telegram\Bot\Api;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

//Check CDR xem số nào không có Contract
function cdr_not_in_contract($db136,$date_check){
    $query = "SELECT
            substring(Caller,3) as Caller,
            date(Time) as Time,
            COUNT(*) AS Total_Calls,
            SUM(duration) AS Total_Duration,
            SUM(cost) AS Total_CustomerCost
        FROM
            `cdr".$date_check."`
        WHERE
            contract_code = '' 
            AND caller_gw NOT LIKE '%TEST%' 
            AND Callee NOT LIKE '1900%' 
            AND call_type LIKE 'OUT%'
            AND Caller NOT LIKE '%555%'
        GROUP BY
            Caller";
    $getResult = $db136->prepare($query);
    $getResult->execute();
    $getResult = $getResult->fetchAll(PDO::FETCH_ASSOC);

    return $getResult; 
}

// Check contract_detail xem trạng thái số
function infoNumber($db_biling,$number)
{
    // $number = 2888891505;
    $query = "SELECT contract_code,customer_code,ext_number,status, date(cancellation_at) AS cancellation_at FROM `contracts_details` 
    WHERE ext_number = $number ORDER BY created_at DESC LIMIT 1;";
    $getResult = $db_biling->prepare($query);
    $getResult->execute();
    $getResult = $getResult->fetch(PDO::FETCH_ASSOC);

    return $getResult; 
}

function sendTele($output)
{
    $chatId = '@test_message123';
    // $filePath = '/root/digitelTool/excel/compareNumberLockVTL/1.xlsx';
    $apiToken = '6369084280:AAFxZhzm4CrUznvj7ZAENCznn3AR-JKqg7M';

    $data = [
        'chat_id' => $chatId,
        'text' => $output,
        'parse_mode' => 'HTML', // Đúng cú pháp
    ];
    $response = file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data));


    return $response;
}


while(true)
{
    //date-1
    $date_check = date("Ymd",strtotime('-1 day'));
    $date_check = '20240807';
    $db136 = connectToDatabase136($host136, $name136, $user136, $password136);
    $db_biling= connectToDatabase($host, $name, $user, $password);
    $cdr_not_in_contract = cdr_not_in_contract($db136,$date_check);
    if(empty($cdr_not_in_contract)){
            break;
    }else{
        foreach($cdr_not_in_contract as $cdr){
            $info = infoNumber($db_biling,$cdr['Caller']);
            $nextDay = date('Y-m-d', strtotime($info['cancellation_at'] . ' +1 day'));
            if($info['status'] != 'actived'){
                if($cdr['Time'] > $nextDay){
                    $headerSend = "CẢNH BÁO ngày: " . $cdr['Time'] . "\n";
                    $bodyContractDetails = "Đầu số: <strong>" . $cdr['Caller']
                    . "</strong> với hợp đồng <b><i>" . $info['contract_code'] 
                    . "</i></b> thanh lý vào ngày: " . $info['cancellation_at'] . "\n";
                    $bodyCDR = "Đã phát sinh cước từ thời điểm: <strong>" . $cdr['Time'] . "</strong> với tổng số cuộc gọi: <strong>" . $cdr['Total_Calls'] . "</strong>, tổng thời lượng <strong>"
                    . $cdr['Total_Duration'] . "s</strong>" . " và tổng cước: <strong>" . number_format($cdr['Total_CustomerCost'], 2) . "đ</strong>" . "\n";
                    $output = $headerSend . $bodyContractDetails . $bodyCDR;
                    sendTele($output);

                }else {
                    $headerSend = "CẢNH BÁO ngày: " . $cdr['Time'] . "\n";
                    $bodyContractDetails = "Đầu số: <strong>" . $cdr['Caller'] . '</strong> chưa có mã hợp đồng!' . "\n";
                    $bodyCDR = "Đã phát sinh cước từ thời điểm: <strong>" . $cdr['Time'] . "</strong> với tổng số cuộc gọi: <strong>" . $cdr['Total_Calls'] . "</strong>, tổng thời lượng <strong>"
                    . $cdr['Total_Duration'] . "s</strong>" . " và tổng cước: <strong>" . number_format($cdr['Total_CustomerCost'], 2) . "đ</strong>" . "\n";
                    $output = $headerSend . $bodyContractDetails . $bodyCDR;
                    sendTele($output);
                }

            }
        }
    }
    break;
}

?>
