<?php


namespace App\Modules\SwsftSolidworks\Controllers;

use App\Modules\SwsftSolidworks\Services\SwsftSolidworksService;
use Illuminate\Support\Facades\Mail;

class SwsftSolidworksController
{
    /**
     * 抓取登记冲突预查页数据
     */
    public function getCheck4ConflictData(){
        $swsftSolidworksService = new SwsftSolidworksService();
        $loginFormDataResult = $swsftSolidworksService->getLoginFormData();
        if($loginFormDataResult['code'] != 0){
            return $loginFormDataResult;
        }
        $loginFormData = $loginFormDataResult['data'];
        unset($loginFormDataResult);

        $cookieResult = $swsftSolidworksService->getLoginCookies($loginFormData);
        if($cookieResult['code'] != 0){
            return $cookieResult;
        }
        $cookie = $cookieResult['data'];
        unset($cookieResult);

        $companyDataResult = $swsftSolidworksService->getCompanyDataForSqlServer();
        if($companyDataResult['code'] != 0){
            return $companyDataResult;
        }
        $companyData = $companyDataResult['data'];
        unset($companyDataResult);

        $check4ConflictFormDataResult = $swsftSolidworksService->getCheck4ConflictFormData($cookie);
        if($check4ConflictFormDataResult['code'] != 0){
            return $check4ConflictFormDataResult;
        }
        $check4ConflictFormData = $check4ConflictFormDataResult['data'];
        unset($check4ConflictFormDataResult);

        $check4ConflictDataResult = $swsftSolidworksService->getCheck4ConflictData($cookie,$companyData,$check4ConflictFormData);
        if($check4ConflictDataResult['code'] != 0){
            return $check4ConflictDataResult;
        }
        $check4ConflictData = $check4ConflictDataResult['data'];
        unset($check4ConflictDataResult);

        $swsftSolidworksService->sendEmail($check4ConflictData);

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => []
        ];
    }


}
