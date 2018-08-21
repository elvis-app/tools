<?php

namespace App\Console\Commands\Tantu\Api;

use Illuminate\Console\Command;

class StatGatewayNum extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:api:statGatewayNum {dir}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计网关已接入接口数量';

    protected $set = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void {
        //
        $dir = $this->argument('dir');
        if (!is_dir($dir)) {
            $this->error('文件夹不存在');
            exit;
        }
        $list = glob($dir . '/*.php');
        $bar = $this->output->createProgressBar(count($list));
        foreach ($list as $val) {
            $this->matchData($val);
            $bar->advance();
        }
        $bar->finish();
        echo "\r\n";

        $total = 0;
        foreach ($this->set as $key => $val) {
            $num = count($val);
            $total += $num;
            $this->info(sprintf('%s已对接%d个', $key, count($val)));
        }
        $this->info(sprintf('网关已对接%d个微服务接口', $total));
        return;
    }

    /**
     * 匹配数据
     * @author  fangjianwei
     * @param string $val
     */
    public function matchData(string $fileName): void {
        if (!is_file($fileName)) return;

        $data = include($fileName);
        $map = [];
        foreach ($data as $val) {
            $url = strtolower(preg_replace('/(v\d+\.\d+)/', '{version}', $val['url']));
            if (empty($map[md5($url)])) {
                $map[md5($url)] = [
                    'url'   => $url,
                    'total' => 0,
                ];
            }
            $map[md5($url)]['total']++;
        }
        $stat = pathinfo($fileName);
        $this->set[$stat['filename']] = $map;
    }
}
