<?php
/**
 * 阿里云DDNS
 *
 * 利用官方SDK实现相关功能，只具备添加、更新
 *
 * 实现流程：
 * 1、初始化相关赋值
 * 2、创建SDK客户端
 * 3、查询RR + Domain
 * 4、不存在则添加，存在则更新
 * 5、完成
 *
 * 运行：
 *
 * php this.php accessKeyId accessKeySecret domain RR type
 *
 * accessKeyId       RAM用户
 * accessKeySecret   RAM密钥
 * domain            域名：xxx.com、xxx.cn等
 * RR                解析名 如：@、www
 * type              解析内容方式：A、AAAA
 *
 *
 * RAM  参考文档：https://help.aliyun.com/document_detail/53045.html?spm=a2c4g.324629.0.0.348f7f80k5BZTc
 *
 */
require_once(__DIR__ . '/vendor/autoload.php');

use AlibabaCloud\SDK\Alidns\V20150109\Models\AddDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeDomainRecordsRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRequest;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\SDK\Alidns\V20150109\Alidns;



class DDNS {
    private $accessKeyId = "";
    private $accessKeySecret = "";

    private $domain =  '';
    private $RR     =  '';
    private $type   =  'A';


    //
    private $client;

    public function __construct($argc, $argv)
    {
        $this->accessKeyId     = $argv[1]??"";
        $this->accessKeySecret = $argv[2]??"";
        $this->domain          = $argv[3]??"";
        $this->RR              = $argv[4]??"@";
        $this->type            = $argv[5]??"A";

        $this->createClient();
    }

    /**
     * 利用SDK创建一个阿里云请求客户端
     * @return void
     */
    private function createClient(){
        $config = new Config([
            // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_ID。
            "accessKeyId" => $this->accessKeyId,
            // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_SECRET。
            "accessKeySecret" => $this->accessKeySecret
        ]);
        // Endpoint 请参考 https://api.aliyun.com/product/Alidns
        $config->endpoint = "alidns.cn-hangzhou.aliyuncs.com";


        $this->client =  new Alidns($config);
    }

    /**
     * 请求URL
     * @param $url
     * @return bool|string
     */
    private function curl($url){
        $cu = curl_init($url);
        //设置  返回请求内容
        curl_setopt($cu,CURLOPT_RETURNTRANSFER,true);
        //执行
        $res = curl_exec($cu);
        curl_close($cu);
        if (false === $res)
            return '';
        return $res;
    }

    /**
     * 通过该连接获取外网IPv4地址
     * @return string
     */
    private function getIpV4(){
        $url = 'https://4.ipw.cn';
        $content = $this->curl($url);
        return $this->curl($url);
    }

    /**
     * 根据该连接获取IPv6地址
     * @return string
     */
    private function getIpV6(){
        $url = 'https://6.ipw.cn';
        return $this->curl($url);
    }


    /**
     * 输出结束
     * @param $msg
     * @return void
     */
    function e(...$msg){
        foreach ($msg as $v){
            var_dump($v);
            echo PHP_EOL;
        }
        die;
    }

    /**
     * 查询对应记录，完全匹配则返回数据，不存在返回false
     * @param $page
     * @return false|mixed|string|void
     */
    public function queryDomain($page = 1){
        if (empty($this->domain)){
            $this->e('空白域名更新不了');
        }
        $describeDomainRecordsRequest = new DescribeDomainRecordsRequest([
            "domainName" => $this->domain,
            "lang"       => "zh",
            "RRKeyWord"  => $this->RR,
            "pageNumber" => $page
        ]);

        $runtime = new RuntimeOptions([]);
        try {
            // 复制代码运行请自行打印 API 的返回值
            $res = $this->client->describeDomainRecordsWithOptions($describeDomainRecordsRequest, $runtime);

            /**
             * 利用RR去搜索，0条记录表示不存在，需要添加
             */
            if (0 === $res->TotalCount || is_null($res->body->domainRecords->record)){
                return false;
            }
            $obj = '';

            foreach ($res->body->domainRecords->record as $v){

                if ($v->RR === $this->RR){
                    $obj = $v;
                    break;
                }
            }
            if (empty($obj)){
                if ($res->PageSize*$res->PageNumber > $res->TotalCount){
                    return $this->queryDomain($page++);
                }
                return false;
            }
            return $obj;
        }
        catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $this->e($error->message,$error->data["Recommend"],Utils::assertAsString($error->message));
        }
    }

    private function plusRecord($ip){
        $addDomainRecordRequest = new AddDomainRecordRequest([
            "domainName" => $this->domain,
            "RR"         => $this->RR,
            "type"       => strtoupper($this->type),
            "value"      => $ip
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            // 复制代码运行请自行打印 API 的返回值
            $this->client->addDomainRecordWithOptions($addDomainRecordRequest, $runtime);
            $this->e('Plus Success',[
                "domainName" => $this->domain,
                "RR"         => $this->RR,
                "type"       => strtoupper($this->type),
                "value"      => $ip
            ]);
        }
        catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $this->e($error->message,$error->data["Recommend"],Utils::assertAsString($error->message));
        }
    }

    /**
     * 更新域名解析
     * @param $query
     * @param $ip
     * @return void
     */
    private function upRecord($query,$ip){
        if (strtoupper($this->type) === $query->type && $ip === $query->value){
            $this->e($this->RR.'.'.$this->domain . ': 旧地址和新地址一样，无需更新: '.$ip);
        }

        $updateDomainRecordRequest = new UpdateDomainRecordRequest([
            "recordId" => $query->recordId,
            "RR"       => $query->RR,//用查询到的 避免更新错误
            "type"     => strtoupper($this->type),
            "value"    => $ip
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            $this->client->updateDomainRecordWithOptions($updateDomainRecordRequest, $runtime);
            $this->e('Upload Success',[
                'domain'=>$query->RR . '.' . $this->domain,
                "old_type" => $query->type,
                "new_type" => strtoupper($this->type),
                "old_value" => $query->value,
                "new_value" => $ip
            ]);
        }
        catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $this->e($error->message,$error->data["Recommend"],Utils::assertAsString($error->message));
        }
    }


    public function run(){
        if (empty($this->accessKeyId) || empty($this->accessKeySecret)){
            $this->e("accessKeyId 和 accessKeySecret 不能为空");
        }

        $ip = '';
        switch (strtoupper($this->type)){
            case 'A':
                $ip = $this->getIpV4();
                break;
            case 'AAAA':
                $ip = $this->getIpV6();
                break;
            default:
                $this->e('DDNS只需要解决IPv4和IPv6');
        }

        $query = $this->queryDomain();

        if (false === $query){
            $this->plusRecord($ip);
        }else{
            $this->upRecord($query,$ip);
        }
    }
}

$ddns = new DDNS($argc,$argv);
$ddns->run();









