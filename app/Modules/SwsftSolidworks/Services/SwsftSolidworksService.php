<?php


namespace App\Modules\SwsftSolidworks\Services;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use phpspider\core\requests;
use phpspider\core\selector;
use Exception;

/*
 * 0        Success
 * 10000    SqlServer connection failed
 * 10001    Unable to retrieve cookie data
 */

class SwsftSolidworksService
{
    const SWSFT_SOLIDWORKS_LOGIN_URL = "https://swsft.solidworks.com.cn/SignIn.aspx";                                       //登录页面地址
    const SWSFT_SOLIDWORKS_CHECK4CONFLICT_URL = "https://swsft.solidworks.com.cn/Saleslead/Check4Conflict.aspx";            //登记冲突检查地址
    const SWSFT_SOLIDWORKS_LOGIN_USERNAME = "dsp@sensnow.com";
    const SWSFT_SOLIDWORKS_LOGIN_PASSWORD = "20shoupeng13";

    public $cookieFile;
    public $logDirectory;

    public function __construct()
    {
        $this->cookieFile = base_path() . "/bootstrap/cache/cookies/swsft_solidworks.cookie";                                                 //cookie文件存放位置（自定义）
        $this->logDirectory = base_path() . "/storage/logs/swsft-solidwords/";
    }

    /**
     * 获取登录页表单数据
     */
    public function getLoginFormData()
    {
        $formData = [];
        $html = requests::get(self::SWSFT_SOLIDWORKS_LOGIN_URL);
        $formData['__VIEWSTATE'] = selector::select($html, "//input[@name='__VIEWSTATE']/@value");
        $formData['__EVENTVALIDATION'] = selector::select($html, "//input[@name='__EVENTVALIDATION']/@value");
        $formData['__VIEWSTATEGENERATOR'] = selector::select($html, "//input[@name='__VIEWSTATEGENERATOR']/@value");
        $formData['__EVENTTARGET'] = selector::select($html, "//input[@name='__EVENTTARGET']/@value");
        $formData['__EVENTARGUMENT'] = selector::select($html, "//input[@name='__EVENTARGUMENT']/@value");
        $formData['ctl00$cphDefault$btnSignIn'] = selector::select($html, "//input[@name='ctl00\$cphDefault\$btnSignIn']/@value");

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => $formData
        ];
    }

    /**
     * 获取登录后的cookie数据
     */
    public function getLoginCookies($loginFormData)
    {
        $loginFormData['ctl00$cphDefault$tbSignInName'] = self::SWSFT_SOLIDWORKS_LOGIN_USERNAME;
        $loginFormData['ctl00$cphDefault$tbPassword'] = self::SWSFT_SOLIDWORKS_LOGIN_PASSWORD;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::SWSFT_SOLIDWORKS_LOGIN_URL);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);                                //post方式提交
        curl_setopt($curl, CURLOPT_POSTFIELDS, $loginFormData);
        curl_exec($curl);
        curl_close($curl);

        $cookieKey = ".SolidWorks.ForecastTool.ManagementSite.Auth";
        $cookieData = file_get_contents($this->cookieFile);
        $cookieKeyLocation = strpos($cookieData, $cookieKey);
        if ($cookieKeyLocation === false) {
            return [
                'code' => 10001,
                'message' => 'Unable to retrieve cookie data',
            ];
        }
        $cookieKeyString = substr($cookieData, $cookieKeyLocation);
        $cookieValueList = explode('	', $cookieKeyString);
        $cookieValue = isset($cookieValueList[1]) ? $cookieValueList[1] : '';
        $cookieValue = str_replace('#HttpOnly_swsft.solidworks.com.cn', '', $cookieValue);
        $cookieValue = trim($cookieValue);
        $cookieValue = strip_tags($cookieValue);
        $cookieValue = str_replace(array("\r\n", "\r", "\n"), '', $cookieValue);

        #返回cookie列表
        $cookie = $cookieKey . "=" . $cookieValue;

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => $cookie
        ];
    }

    /**
     * 获取sqlserver中的数据
     */
    public function getCompanyDataForSqlServer()
    {
        $sqlServerConn = sqlsrv_connect(env('SERVER_DB_HOST'), [
            'Database' => env('SERVER_DB_DATABASE'),
            'UID' => env('SERVER_DB_USERNAME'),
            'PWD' => env('SERVER_DB_PASSWORD'),
            'CharacterSet' => 'UTF-8',
        ]);

        $sql = "SELECT * FROM clientInfo";

        $companyDataResource = sqlsrv_query($sqlServerConn, $sql);
        if ($companyDataResource === false) {
            file_put_contents($this->logDirectory . '/sqlServerError.log', date("Y-m-d H:i:s") . '   ' . var_export(sqlsrv_errors(), true) . "\r\n", FILE_APPEND);
            return [
                'code' => 10000,
                'message' => 'SqlServer connection failed',
            ];
        }
        $sqlCompanyList = [];
        while ($row = sqlsrv_fetch_array($companyDataResource, SQLSRV_FETCH_ASSOC)) {
            $sqlCompany['Id'] = iconv("GBK", "UTF-8", $row['Id']);                                            //Id
            $sqlCompany['clinetName'] = $row['clinetName'];                            //公司名称
            $sqlCompany['swStatus'] = $row['swStatus'];                                //今天sw状态
            $sqlCompany['swNumber'] = $row['swNumber'];                                //今天sw冲突数量
            $sqlCompany['epdmStatus'] = $row['epdmStatus'];                            //今天sw状态
            $sqlCompany['epdmNumber'] = $row['epdmNumber'];                            //今天sw冲突数量
            $sqlCompany['orgSwStatus'] = $row['orgSwStatus'];                          //昨天sw状态
            $sqlCompany['orgSwNumber'] = $row['orgSwNumber'];                          //昨天sw冲突数量
            $sqlCompany['orgEpdmStatus'] = $row['orgEpdmStatus'];                      //昨天sw状态
            $sqlCompany['orgEpdmNumber'] = $row['orgEpdmNumber'];                      //昨天sw冲突数量
            $sqlCompany['salesName'] = $row['salesName'];                              //销售人员
            $sqlCompany['emailStr'] = $row['emailStr'];                                //销售人员email

            $sqlCompanyList[] = $sqlCompany;
        }

        #释放指定资源
        sqlsrv_free_stmt($companyDataResource);
        sqlsrv_close($sqlServerConn);

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => $sqlCompanyList
        ];
    }

    /**
     * 获取登记冲突预查页表单数据
     */
    public function getCheck4ConflictFormData($cookie)
    {
        $formData = [];
        requests::set_header('cookie', $cookie);
        $html = requests::get(self::SWSFT_SOLIDWORKS_CHECK4CONFLICT_URL);
        $formData['__EVENTTARGET'] = selector::select($html, "//input[@name='__EVENTTARGET']/@value");
        $formData['__EVENTARGUMENT'] = selector::select($html, "//input[@name='__EVENTARGUMENT']/@value");
        $formData['__VIEWSTATE'] = selector::select($html, "//input[@name='__VIEWSTATE']/@value");
        $formData['__VIEWSTATEGENERATOR'] = selector::select($html, "//input[@name='__VIEWSTATEGENERATOR']/@value");
        $formData['__EVENTVALIDATION'] = selector::select($html, "//input[@name='__EVENTVALIDATION']/@value");
        $formData['ctl00$cphDefault$btnQuery'] = selector::select($html, "//input[@name='ctl00\$cphDefault\$btnQuery']/@value");
        $formData['ctl00$cphDefault$tbAddress'] = '';
        $formData['ctl00$cphDefault$tbPhone'] = '';
        $formData['ctl00$cphDefault$tbContactName'] = '';

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => $formData
        ];
    }

    /**
     * 抓取登记冲突预查页数据
     *
     * SW       1
     * EPDM     5
     *
     * @return array  返回比对数据结果
     */
    public function getCheck4ConflictData($cookie, $companyData, $check4ConflictFormData)
    {
        $sqlServerConn = sqlsrv_connect(env('SERVER_DB_HOST'), [
            'Database' => env('SERVER_DB_DATABASE'),
            'UID' => env('SERVER_DB_USERNAME'),
            'PWD' => env('SERVER_DB_PASSWORD'),
            'CharacterSet' => 'UTF-8',
        ]);

        $productType = [
            'ctl00$cphDefault$cblProduct$0' => 1,                                                       //sw
            'ctl00$cphDefault$cblProduct$4' => 5                                                        //epdm
        ];
        $postFormData = $check4ConflictFormData;
        $lastTime = date('Y-m-d H:i:s');

        $compareResult = [];
        foreach ($productType as $typeKey => $typeValue) {
            $postFormData[$typeKey] = $typeValue;
            foreach ($companyData as $companyKey => $companyValue) {
//                sleep(5);
                $postFormData['ctl00$cphDefault$tbName'] = $companyValue['clinetName'];
                requests::set_header('cookie', $cookie);
                $result = requests::post(self::SWSFT_SOLIDWORKS_CHECK4CONFLICT_URL, $postFormData);

                #获取查询结果
                $searchResult = selector::select($result, '//*[@id="cphDefault_gvSaleslead"]/tr/td');
                #没有返回数据，进行下一个公司
                if (!is_array($searchResult)) {
                    continue;
                }
                $searchResultRecords = array_chunk($searchResult, 3);
                $statusData = [];
                $numberData = [];
                foreach ($searchResultRecords as $recordKey => $recordValue) {
                    $area = $recordValue[0];                                                    //区域
                    $status = $recordValue[1];                                                  //状态
                    $conflictNumber = $recordValue[2];                                          //冲突数量

                    $statusData[] = [
                        $area => $status
                    ];
                    $numberData[] = [
                        $area => $conflictNumber
                    ];
                }
                $updateSql = '';
                $todayData = [];
                $yesterdayData = [];
                switch ($typeValue) {
                    case 1:
                        $swStatusJson = json_encode($statusData);
                        $swNumberJson = json_encode($numberData);
                        $updateSql = "update clientInfo set swStatus = '{$swStatusJson}',swNumber = '{$swNumberJson}'," .
                            " orgSwStatus = '{$companyValue['swStatus']}',orgSwNumber = '{$companyValue['swNumber']}',lastTime = '{$lastTime}' where Id = '{$companyValue['Id']}'";

                        $todayData = [
                            'swStatus' => $swStatusJson,
                            'swNumber' => $swNumberJson,
                        ];
                        $yesterdayData = [
                            'orgSwStatus' => $companyValue['swStatus'],
                            'orgSwNumber' => $companyValue['swNumber']
                        ];
                        break;
                    case 5:
                        $epdmStatusJson = json_encode($statusData);
                        $epdmNumberJson = json_encode($numberData);
                        $updateSql = "update clientInfo set epdmStatus = '{$epdmStatusJson}',epdmNumber = '{$epdmNumberJson}'," .
                            " orgEpdmStatus = '{$companyValue['epdmStatus']}',orgEpdmNumber = '{$companyValue['epdmNumber']}',lastTime = '{$lastTime}' where Id = '{$companyValue['Id']}'";
                        $todayData = [
                            'epdmStatus' => $epdmStatusJson,
                            'epdmNumber' => $epdmNumberJson,
                        ];
                        $yesterdayData = [
                            'orgEpdmStatus' => $companyValue['epdmStatus'],
                            'orgEpdmNumber' => $companyValue['epdmNumber']
                        ];
                        break;
                }
                /*
                 * 执行sql更新操作
                 */
                if ($stmt = sqlsrv_prepare($sqlServerConn, $updateSql)) {
                    echo $companyValue['clinetName'] . ' type : ' . $typeValue . " : Statement prepared.\r\n";
                } else {
                    echo $companyValue['clinetName'] . ' type : ' . $typeValue . " : Statement could not be prepared.\r\n";;
                    die(print_r(sqlsrv_errors(), true));
                }

                /* Execute the statement. */
                if ($executeResult = sqlsrv_execute($stmt)) {
                    echo $companyValue['clinetName'] . ' type : ' . $typeValue . " : Statement executed.\r\n";
                } else {
                    echo $companyValue['clinetName'] . ' type : ' . $typeValue . " : Statement could not be executed.\r\n";
                    die(print_r(sqlsrv_errors(), true));
                }

                /* Free the statement and connection resources. */
                sqlsrv_free_stmt($stmt);

                /*
                 * 进行数据比对
                 */
                if ($this->compareData($typeValue, $yesterdayData, $todayData)) {
                    $compareResult[$companyValue['salesName']][] = [
                        'type' => $typeValue,
                        'companyName' => $companyValue['clinetName'],
                        'updatedAt' => $lastTime,
                        'today' => $todayData,
                        'yesterday' => $yesterdayData,
                        'emailStr' => $companyValue['emailStr']
                    ];
                }
            }
            #删除之前配置的type选项
            unset($postFormData[$typeKey]);
        }
        sqlsrv_close($sqlServerConn);
        return [
            'code' => 0,
            'message' => 'Success',
            'data' => $compareResult
        ];
    }

    /**
     * 比对数据
     *
     * 根据负责人进行分组，每个负责人的公司数据进行比对，然后给负责人发送一封统一的邮件
     *
     * @return boolean          true有变动，false无变动
     */
    private function compareData($type, $yesterday, $today)
    {
        switch ($type) {
            case 1:
                $todayStatus = json_decode($today['swStatus'], 1);
                $todayNumber = json_decode($today['swNumber'],1);
                $yesterdayOrgStatus = json_decode($yesterday['orgSwStatus'], 1);
                $yesterdayOrgNumber = json_decode($yesterday['orgSwNumber'], 1);
                break;
            case 5:
                $todayStatus = json_decode($today['epdmStatus'], 1);
                $todayNumber = json_decode($today['epdmNumber'], 1);
                $yesterdayOrgStatus = json_decode($yesterday['orgEpdmStatus'], 1);
                $yesterdayOrgNumber = json_decode($yesterday['orgEpdmNumber'], 1);
                break;
        }
        /*********************************************************************************状态判断************************************************************************************/
        #昨天有数据  今天有数据
        if (!empty($todayStatus) && !empty($yesterdayOrgStatus)) {
            #数量不对，认为是有变动
            if (count($todayStatus) != count($yesterdayOrgStatus)) {
                return true;
            }
            #数量相等，比对键值
            if (count($todayStatus) == count($yesterdayOrgStatus)) {
                for ($i = 0; $i < count($todayStatus); $i++) {
                    foreach ($todayStatus[$i] as $todayStatusKey => $todayStatusValue) {
                        #检查昨天的数据中是否有今天的键值
                        if (isset($yesterdayOrgStatus[$i][$todayStatusKey])) {
                            #如果区域名称相同
                            if ($todayStatusValue != $yesterdayOrgStatus[$i][$todayStatusKey]) {
                                return true;
                            }
                        } else {
                            #如果区域名称不相同，认为是变动
                            return true;
                        }
                    }
                }
            }
        }
        #昨天有数据  今天没数据
        if (empty($todayStatus) && !empty($yesterdayOrgStatus)) {
            return true;
        }
        #昨天没数据  今天有数据
        if (!empty($todayStatus) && empty($yesterdayOrgStatus)) {
            return true;
        }
        /*********************************************************************************数量判断************************************************************************************/
        #昨天有数据  今天有数据
        if (!empty($todayNumber) && !empty($yesterdayOrgNumber)) {
            #数量不对，认为是有变动
            if (count($todayNumber) != count($yesterdayOrgNumber)) {
                return true;
            }
            #数量相等，比对键值
            if (count($todayNumber) == count($yesterdayOrgNumber)) {
                for ($i = 0; $i < count($todayNumber); $i++) {
                    foreach ($todayNumber[$i] as $todayNumberKey => $todayNumberValue) {
                        #检查昨天的数据中是否有今天的键值
                        if (isset($yesterdayOrgNumber[$i][$todayNumberKey])) {
                            #如果区域名称相同
                            if ($todayNumberValue != $yesterdayOrgNumber[$i][$todayNumberKey]) {
                                return true;
                            }
                        } else {
                            #如果区域名称不相同，认为是变动
                            return true;
                        }
                    }
                }
            }
        }
        #昨天有数据  今天没数据
        if (empty($todayNumber) && !empty($yesterdayOrgNumber)) {
            return true;
        }
        #昨天没数据  今天有数据
        if (!empty($todayNumber) && empty($yesterdayOrgNumber)) {
            return true;
        }
        return false;
    }

    /**
     * 发送邮件
     */
    public function sendEmail($companyList = [])
    {
        try {
            if (empty($companyList)) {
                $bodyHtml = "报备信息无变动数据";
            } else {
                foreach ($companyList as $key => $value) {
                    $bodyHtml = "";
                    $bodyHtml .=
                        <<<EOF
<style type="text/css">
	.DefaultTable {
    border-right: #d3d3d3 2px groove;
    border-top: #d3d3d3 2px groove;
    border-left: #d3d3d3 2px groove;
    border-bottom: #d3d3d3 2px groove;
    border-collapse: collapse;
    }
    table {
        width: 100%;
        display: table;
        border-collapse: separate;
        border-spacing: 2px;
        border-color: grey;

        border-collapse: separate;
        border-spacing: 2px;
    }
    div {
        font-family: Arial;
        font-size: 12px;
    }
    td {
        font-family: Arial;
        font-size: 12px;

        display: table-cell;
        vertical-align: inherit;
    }
    .DefaultTable thead {
        color: white;
        background-color: #666666;
        font-weight: bold;
    }
    .DefaultTable {
        border-right: #d3d3d3 2px groove;
        border-top: #d3d3d3 2px groove;
        border-left: #d3d3d3 2px groove;
        border-bottom: #d3d3d3 2px groove;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    tr {
        display: table-row;
        vertical-align: inherit;
        border-color: grey;
    }
    div {
        font-family: Arial;
        font-size: 12px;
    }
    tbody{
        border-color: grey;
    }
    .title{
        margin-top: 5px;
        margin-bottom: 2px;
        font-weight: 700;
        color: #666666;
    }

</style>
                    <p>您好：{$key}，以下为报备信息变动数据</p>
EOF;
                    $counter = 0;
                    foreach ($value as $vkey => $vvalue) {
                        $counter = $counter + 1;

                        #sw
                        if ($vvalue['type'] == 1) {
                            $titleType = 'SW';
                            $todayStatus = !empty($vvalue['today']['swStatus']) ? json_decode($vvalue['today']['swStatus'], 1) : '';
                            $todayNumber = !empty($vvalue['today']['swNumber']) ? json_decode($vvalue['today']['swNumber'], 1) : '';
                            $yesterdayOrgStatus = !empty($vvalue['yesterday']['orgSwStatus']) ? json_decode($vvalue['yesterday']['orgSwStatus'], 1) : '';
                            $yesterdayOrgNumber = !empty($vvalue['yesterday']['orgSwNumber']) ? json_decode($vvalue['yesterday']['orgSwNumber'], 1) : '';
                            #epdm
                        } elseif ($vvalue['type'] == 5) {
                            $titleType = 'EPDM';
                            $todayStatus = !empty($vvalue['today']['epdmStatus']) ? json_decode($vvalue['today']['epdmStatus'], 1) : '';
                            $todayNumber = !empty($vvalue['today']['epdmNumber']) ? json_decode($vvalue['today']['epdmNumber'], 1) : '';
                            $yesterdayOrgStatus = !empty($vvalue['today']['orgEpdmStatus']) ? json_decode($vvalue['yesterday']['orgEpdmStatus'], 1) : '';
                            $yesterdayOrgNumber = !empty($vvalue['today']['orgEpdmNumber']) ? json_decode($vvalue['yesterday']['orgEpdmNumber'], 1) : '';
                        }

                        $bodyHtml .=
                            <<<EOF
                    <table class="DefaultTable" width="100%" cellpadding="2" border="2">
                            <thead>
                                <tr class="firstRow">
                                    <td colspan="2">{$counter}、{$vvalue['companyName']}（数据更新时间:{$vvalue['updatedAt']}）</td></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="2">
                                        <div>

                    <div class="title">{$titleType}</div>
                        <table cellspacing="0" rules="all" border="1" style="width:100%;border-collapse:collapse;">
                            <tbody>
                                <tr>
                                    <th align="left" scope="col" style="white-space:nowrap; font-size: 14px; width: 20%;">区域</th>
                                    <th align="left" scope="col" style="white-space:nowrap; font-size: 14px; width: 40%;">起始状态/结束状态</th>
                                    <th align="left" scope="col" style="white-space:nowrap; font-size: 14px; width: 40%;">起始冲突数量/结束冲突数量</th>
                                </tr>
EOF;
                        foreach ($todayStatus as $todayStatusKey => $todayStatusValue) {
                            foreach ($todayStatusValue as $todayStatusValueKey => $todayStatusValueValue) {
                                $bodyHtml .=
                                    <<<EOF
                    <tr>
                        <td style="white-space:nowrap;">/{$todayStatusValueKey}</td>
                        <td style="white-space:nowrap;">/{$todayStatusValueValue}</td>
                        <td style="white-space:nowrap;">/{$todayNumber[$todayStatusKey][$todayStatusValueKey]}</td>
                    </tr>
EOF;
                            }
                        }
                        $bodyHtml .=
                            <<<EOF
                    </tbody></table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
EOF;
                    }
                    $mail = new PHPMailer(true);
                    $mail->CharSet = 'UTF-8';

                    $todayDate = date('Y-m-d');
                    $subject = $todayDate . ' Solidworks登记冲突检查';

                    #发送邮箱
                    $fromEmailName = env("MAIL_USERNAME");
                    $fromEmailPwd = env("MAIL_PASSWORD");
                    $formEmailHost = env("MAIL_HOST");
                    #接受邮箱列表
                    $mail->addAddress($vvalue['emailStr'], $key);                      // Add a recipient
                    /*
                     * 如果不是刘小娟，需要抄送给刘小娟一份
                     */
                    if($vvalue['emailStr'] != 'lxj@sensnow.com'){
                        $mail->addCC('lxj@sensnow.com', $key);
                    }

                    //Server settings
                    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
                    $mail->Mailer = env("MAIL_MAILER");                    // Send using SMTP
                    $mail->Host = $formEmailHost;                               // Set the SMTP server to send through
                    $mail->SMTPAuth = true;                                     // Enable SMTP authentication
                    $mail->Username = $fromEmailName;                           // SMTP username
                    $mail->Password = $fromEmailPwd;                            // SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
                    $mail->Port = env("MAIL_PORT");                         // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

                    //Recipients
                    $mail->setFrom($fromEmailName, "Solidworks登记冲突检查");

                    // Content
                    $mail->isHTML(true);                                // Set email format to HTML
                    $mail->Subject = $subject;
                    $mail->Body = $bodyHtml;

                    if (!$mail->send()) {
                        file_put_contents($this->logDirectory . '/emailSendError.log', date("Y-m-d H:i:s") . '   ' . $mail->ErrorInfo . "\r\n", FILE_APPEND);
                    }
                    sleep(20);
                }
            }


        } catch (Exception $e) {
            file_put_contents($this->logDirectory . '/emailSendError.log', date("Y-m-d H:i:s") . '   ' . $e->getLine().' '.$e->getMessage() . "\r\n", FILE_APPEND);
        }
    }
}
