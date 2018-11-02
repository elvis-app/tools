<?php

namespace App\Console\Commands\Tantu\Maker\TantuServer;

use Illuminate\Console\Command;

class Service extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:Maker:TantuServer:Service {file_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '探途server生成controller';

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
    public function handle() {
        $path = $this->argument('file_name');
        $pathInfo = pathinfo($path);
        $fileName = $pathInfo['basename'];
        $className = $pathInfo['filename'];
        if (empty($fileName)) {
            $this->error('参数格式错误');
            return;
        }
        preg_match('/(services.*)/i', $pathInfo['dirname'], $fileInfo);
        if (empty($fileInfo[1])) {
            $this->error('参数格式错误');
            return;
        }
        $fileInfo[1] = str_replace('services\\', '', $fileInfo[1]);
        $namespace = str_replace('/', '\\', $fileInfo[1]);
        $template = $this->template($className, $namespace);
        !is_dir($pathInfo['dirname']) && mkdir($pathInfo['dirname'], 0755, true);
        file_put_contents($pathInfo['dirname'].'/'.$pathInfo['basename'], $template);
    }


    public function template(string $className, string $namespace):string{
        $now = date('Y-m-d H:i');
        return <<<END
<?php
/**
 * @datetime  {$now}
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace TantuTravelApi\Services\\{$namespace};

use TantuTravelApi\Services\ServicesBase;

/**
 * Class {$className}
 * @package TantuTravelApi\Services\\{$namespace}
 * @author  fangjianwei
 */
class {$className} extends ServicesBase {

    public function __construct() {
        parent::__construct();
    }
}
END;

    }
}
