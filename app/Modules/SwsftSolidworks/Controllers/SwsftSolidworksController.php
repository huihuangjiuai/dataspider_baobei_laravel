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
        $loginFormData = $swsftSolidworksService->getLoginFormData();
        if($loginFormData['code'] != 0){
            return $loginFormData;
        }
        $loginFormData = $loginFormData['data'];

        $cookie = $swsftSolidworksService->getLoginCookies($loginFormData);
        if($cookie['code'] != 0){
            return $cookie;
        }
        $cookie = $cookie['data'];

        $companyData = $swsftSolidworksService->getCompanyDataForSqlServer();
        if($companyData['code'] != 0){
            return $companyData;
        }
        $companyData = $companyData['data'];

        $check4ConflictFormData = $swsftSolidworksService->getCheck4ConflictFormData($cookie);
        if($check4ConflictFormData['code'] != 0){
            return $check4ConflictFormData;
        }
        $check4ConflictFormData = $check4ConflictFormData['data'];

        $check4ConflictData = $swsftSolidworksService->getCheck4ConflictData($cookie,$companyData,$check4ConflictFormData);
        if($check4ConflictData['code'] != 0){
            return $check4ConflictData;
        }
        $check4ConflictData = $check4ConflictData['data'];

        $swsftSolidworksService->sendEmail($check4ConflictData);

        return [
            'code' => 0,
            'message' => 'Success',
            'data' => []
        ];
    }


}
