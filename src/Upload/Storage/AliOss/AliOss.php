<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/6/4
 * Time: 10:10
 */

namespace Upload\Storage\AliOss;


use OSS\Core\OssException;
use OSS\OssClient;
use Upload\Exception;
use Upload\StorageInterface;

class AliOss implements StorageInterface
{
    private $config;
    private $directory;
    private $overwrite;

    public function __construct(Config $config, string $directory, bool $overwrite = false)
    {
        $this->config = $config;
        $this->directory = $directory;
        $this->overwrite = $overwrite;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function upload(\Upload\FileInfoInterface $fileInfo)
    {
        $ossClient = new OssClient(
            $this->config->getAccessKeyId(),
            $this->config->getAccessKeySecret(),
            $this->config->getEndPoint(),
            $this->config->getIsCName(),
            $this->config->getSecurityToken(),
            $this->config->getRequestProxy());
        //上传
        $destinationFile = $this->directory . $fileInfo->getNameWithExtension();
        if ($this->overwrite === false && $ossClient->doesObjectExist($this->config->getBucket(), $destinationFile) === true) {
            throw new \Upload\Exception('File already exists', $fileInfo);
        }
        try {
            $ossClient->uploadFile($this->config->getBucket(), $destinationFile, $fileInfo->getPathname());
        } catch (OssException $e) {
            throw new Exception($e->getMessage());
        }
    }
}