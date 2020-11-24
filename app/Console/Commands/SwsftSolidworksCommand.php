<?php

namespace App\Console\Commands;

use App\Modules\SwsftSolidworks\Controllers\SwsftSolidworksController;
use Illuminate\Console\Command;

class SwsftSolidworksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:swsft-solidworks-check4Conflict';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '抓取solidworks报备系统数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit','2048M');
        $swsftSolidworksController = new SwsftSolidworksController();
        $result = $swsftSolidworksController->getCheck4ConflictData();
        if($result['code'] != 0){
            $this->error($result['message']);
        }else{
            $this->info('Success');
        }
    }
}
