<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/6/4
 * Time: 13:44
 */

namespace App\HttpController;

use EasySwoole\Http\AbstractInterface\Controller;
use Upload\Storage\AliOss\Config;

require_once 'vendor/autoload.php';


class Index extends Controller
{

    function index()
    {
        // TODO: Implement index() method
        $this->response()->write('hello world');
        $this->response()->setCookie('a', 'a', time() + 3600);
    }

    function testSession()
    {
        $this->session()->start();
        $this->session()->set('a', time());
    }

    function testSession2()
    {
        $this->session()->start();
        $this->response()->write($this->session()->get('a'));
    }

    function testException()
    {
        new NoneClass();
    }

    public function testAliUpload()
    {
        $directory = 'xxxxx';
        $config = new Config([
            'accessKeyId' => '',
            'accessKeySecret' => '',
            'endpoint' => '',
            'bucket' => '',
            'isCName' => true]);
        $storage = new \Upload\Storage\AliOss\AliOss($config, $directory);
        $file = new \Upload\File('foo', $storage, $this->request()->getSwooleRequest()->files);
        $new_filename = uniqid();
        $file->setName($new_filename);
        $file->addValidations(array(
            new \Upload\Validation\Mimetype(['image/png', 'image/jpeg']),
            new \Upload\Validation\Size('5M')
        ));
        $data = array(
            'name' => $file->getNameWithExtension(),
            'extension' => $file->getExtension(),
            'mime' => $file->getMimetype(),
            'size' => $file->getSize(),
            'md5' => $file->getMd5(),
            'dimensions' => $file->getDimensions(),
            'url' => $config->getEndPoint() . '/' . $directory . $file->getNameWithExtension()
        );

        try {
            $file->upload();
        } catch (\Throwable $e) {
            $errors = $file->getErrors();
            var_dump($errors);
        }
        $this->response()->write(json_encode($data));
    }

    public function testLocalUpload()
    {

        $directory = __DIR__ . '/Uploads';
        $storage = new \Upload\Storage\FileSystem($directory);
        $file = new \Upload\File('foo', $storage, $this->request()->getSwooleRequest()->files);
        $new_filename = uniqid();
        $file->setName($new_filename);
        $file->addValidations(array(
            new \Upload\Validation\Mimetype(['image/png', 'image/jpeg']),
            new \Upload\Validation\Size('5M')
        ));
        $data = array(
            'name' => $file->getNameWithExtension(),
            'extension' => $file->getExtension(),
            'mime' => $file->getMimetype(),
            'size' => $file->getSize(),
            'md5' => $file->getMd5(),
            'dimensions' => $file->getDimensions(),
            'url' => $directory . $file->getNameWithExtension()
        );
        try {
            $file->upload();
        } catch (\Throwable $e) {
            $errors = $file->getErrors();
            var_dump($errors);
        }
        $this->response()->write(json_encode($data));
    }


    protected function onException(\Throwable $throwable): void
    {
        $this->response()->write($throwable->getMessage());
    }

    protected function gc()
    {
        parent::gc();
        var_dump('class :' . static::class . ' is recycle to pool');
    }
}


$http = new \swoole_http_server("0.0.0.0", 9501);
$http->set([
    'worker_num' => 1,
    'package_max_length' => 3096735
]);

$http->on("start", function ($server) {
    echo "Swoole http server is started at http://127.0.0.1:9501\n";
});

$service = new \EasySwoole\Http\WebService();
$service->setExceptionHandler(function (\Throwable $throwable, \EasySwoole\Http\Request $request, \EasySwoole\Http\Response $response) {
    $response->write('error:' . $throwable->getMessage());
});

$http->on("request", function ($request, $response) use ($service) {
    $req = new \EasySwoole\Http\Request($request);
    $service->onRequest($req, new \EasySwoole\Http\Response($response));
});

$http->start();