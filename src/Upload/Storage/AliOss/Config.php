<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/6/4
 * Time: 10:28
 */

namespace Upload\Storage\AliOss;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $accessKeyId;
    protected $accessKeySecret;
    protected $endpoint;
    protected $bucket;
    protected $isCName;
    protected $securityToken;
    protected $requestProxy;

    public function setAccessKeyId(string $accessKeyId)
    {
        $this->accessKeyId = $accessKeyId;
    }

    public function getAccessKeyId()
    {
        return $this->accessKeyId;
    }

    public function setAccessKeySecret(string $accessKeySecret)
    {
        $this->accessKeySecret = $accessKeySecret;
    }

    public function getAccessKeySecret()
    {
        return $this->accessKeySecret;
    }

    public function setEndPoint(string $endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function getEndPoint()
    {
        return $this->endpoint;
    }

    public function setBucket(string $bucket)
    {
        $this->bucket = $bucket;
    }

    public function getBucket()
    {
        return $this->bucket;
    }

    public function setIsCName(bool $isCName)
    {
        $this->isCName = $isCName;
    }

    public function getIsCName()
    {
        return $this->isCName;
    }

    public function setSecurityToken(string $securityToken)
    {
        $this->securityToken = $securityToken;
    }

    public function getSecurityToken()
    {
        return $this->securityToken;
    }

    public function setRequestProxy(string $requestProxy)
    {
        $this->requestProxy = $requestProxy;
    }

    public function getRequestProxy()
    {
        return $this->requestProxy;
    }

    public function initialize(): void
    {
        if (empty($this->isCName)) {
            $this->isCName = false;
        }
    }
}