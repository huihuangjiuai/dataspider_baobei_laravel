<?php


namespace App\Services;


class SqlServerService
{
    /**
     * 查询SqlServer中的公司数据，与抓取数据进行比对
     */
    public static function getCompanyDataForSqlServer()
    {
        $databases = include(__PATH__."/configs/database.php");
        $sqlServerDbConfig = $databases['ismartMemberService'];
        $sqlServerConn = sqlsrv_connect($sqlServerDbConfig['host'], ['Database' => 'ismartMemberService', 'UID' => $sqlServerDbConfig['user'], 'PWD' => $sqlServerDbConfig['pass']]);
        $sql = "select * from CRMCustomer";
        $companyDataResource = sqlsrv_query($sqlServerConn, $sql);
        if ($companyDataResource === false) {
            file_put_contents('logs/sqlServerError.log', date("Y-m-d H:i:s") . '   ' . var_export(sqlsrv_errors(), true) . "\r\n", FILE_APPEND);
            return [];
        }
        $sqlCompanyList = [];
        while ($row = sqlsrv_fetch_array($companyDataResource, SQLSRV_FETCH_ASSOC)) {
            $sqlCompany['FullName'] = iconv("GBK", "UTF-8", $row['FullName']);
            $sqlCompany['ShortName'] = iconv("GBK", "UTF-8", $row['ShortName']);
            $sqlCompany['Contacts'] = iconv("GBK", "UTF-8", $row['Contacts']);
            $sqlCompany['PhoneNum'] = iconv("GBK", "UTF-8", $row['PhoneNum']);

            $sqlCompanyList[] = $sqlCompany;
        }

        #释放指定资源
        sqlsrv_free_stmt($companyDataResource);
        sqlsrv_close($sqlServerConn);

        return $sqlCompanyList;
    }
}
