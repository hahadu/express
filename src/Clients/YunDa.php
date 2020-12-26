<?php

namespace Hahadu\Express\Clients;

use Hahadu\Helper\StringHelper;
use Hahadu\Helper\HttpHelper;
use Hahadu\Helper\XMLHelper;
use think\response\Json;
use Hahadu\Express\Builds\BuildMessage;


class YunDa
{
    protected $api_dir = 'baima-api/api/public';
    protected $api_url = 'pub_order/index';
    protected $version = '1.0';
    protected $partnerid;
    protected $password;
    protected $request;
    protected $validation;
    protected $order_type = "common";
    private $xml_root_name = 'orders';
    private $xml_item_name = 'order';
    private $xml_id_name = 'order';
    private $api_host = '';

    public function __construct($partnerid, $password, $api_host)
    {
        $this->partnerid = $partnerid;
        $this->password = $password;
        $this->api_host = $api_host;
    }

    protected function uri()
    {
        return $this->api_host . DIRECTORY_SEPARATOR . $this->api_dir . DIRECTORY_SEPARATOR . $this->api_url;
    }

    /*****
     * 查询面单余额
     * @param string $type
     * @return array free_num 免费单号数量 | remain_num 付费单号数量
     */
    public function surplusQuery($type='common'){
        $request = 'surplusQuery';
        $this->xml_root_name = 'mailno';
        $this->order_type = $type;
        $data = ['type'=>$type];
        $response = $this->request_yunda($data, $request);
        return [
            'code'=>$response->status,
            'message'=>$response->msg,
            'free_num'=>$response->free_num,
            'remain_num'=>$response->remain_num,
        ];
    }

    /*****
     * 取消订单
     * @param string $order_serial_no 唯一订单号
     * @param string $mail_no 运单号|可选
     * @return mixed
     */
    public function cancelOrder($order_serial_no,$mail_no=''){
        $request = 'cancelOrder';
        $data['order_serial_no'] = $order_serial_no;
        if($mail_no) {
            $data['mail_no'] = $mail_no;
        }
        return $this->request_yunda([$data], $request);
    }

    /******
     * 订单查询接口
     * 订单状态    说明
     * @return
     * accept    接单成功
     * withdraw  订单已取消
     * create    订单创建
     * refuse    地址不送达
     * @param string $order_serial_no 唯一订单号
     * @param string|null $mail_no 快递单号，可选
     * @param int $json_data 是否显示json数据
     * @param int $json_encrypt 是否
     * @return array
     */
    public function queryOrder($order_serial_no,$express_no=null,$json_data=1,$json_encrypt=0)
    {
        $request = "queryOrder";
        $data = [
            'order_serial_no' => $order_serial_no,
            'mailno'  =>  $express_no,
            'json_data' => $json_data,
            'json_encrypt' => $json_encrypt,
        ];

        $response = $this->request_yunda([$data], $request);

        return [
            'code'=>isset($response->status)?$response->status : '',
            'order_serial_no'=>isset($response->order_serial_no) ? $response->order_serial_no : '',
            'express_no'=>isset($response->mailno) ? $response->mailno : '',
            'express_no_info'=>isset($response->json_data)?$response->json_data:'',
            'order_status'=>isset($response->order_status)?$response->order_status:'',
            'message'=>isset($response->msg)?$response->msg:'',
        ];
    }

    /*****
     * 订单创建接口
     * @param array $data 多维数组 [array],多个订单[array1,array2]
     * @return  array order_no 订单号 | express_no 快递单号 | express_no_info 电子面单信息 | code 状态码 | message 消息
     *
     */
    public function generalOrderApi($data)
    {
        $request = 'generalOrderApi';
        $check = $this->check_item_info($data, 0);
        if ($check) {
            return $check;
        }
        $response = $this->request_yunda($data, $request);

        if (is_array($response)) {
            $result = [];
            foreach ($response as $key => $v) {
                $result[$key] = [
                    'order_no' => $v->order_serial_no,
                    'express_no' => $v->mail_no,
                    'express_no_info' => $v->pdf_info,
                    'code' => $v->status,
                    'message' => $v->msg,
                ];
            }
        } else {
            $result[] = BuildMessage::build_express_no($response->order_serial_no,$response->mail_no,$response->pdf_info,$response->status,$response->msg);
        }
        return $result;
    }

    /*******
     * 订单更新
     */
    public function generalUpdateOrder($data){
        $request = 'generalUpdateOrder';

        $check = $this->check_item_info($data, 0);
        if ($check) {
            return $check;
        }
        $response = $this->request_yunda($data, $request);
        $result = [
            'order_no' => $response->order_serial_no,
            'express_no' => $response->mailno,
            'express_no_info' => $response->pdf_info,
            'code' => $response->status,
            'message' => $response->msg,
        ];
        return $result;
    }

    /****
     * 发送请求
     * @param array $data
     * @param string $request
     * @param string $method
     * @return mixed
     * @var  request_data $参数名 必选  说明
     * @var  request_data $partnerid 是    合作商 ID，由韵达提供给大客户
     * @var  request_data $version 否    请求的版本，当前为 1.0
     * @var  request_data $request 是    数据请求类型，如 request=generalOrderApi；其中 generalOrderApi 表示调用通用型下单接口，详细请见各接口 request 定义字典表
     * @var  request_data $xmldata 是    业务参数，XML 数据内容，具体传递字段详见各接口定义，此参数传输的时候需进行BASE64编码
     * @var  request_data $validation 是    签名，计算方式为 md5(xmldata + partnerid + 密码)，xmldata需进行BASE64编码
     */
    protected function request_yunda($data, $request, $method = 'post')
    {
        $xml_data = $this->build_xml($data);
        $this->validation = strtolower(md5($xml_data . $this->partnerid . $this->password));
        /****
         * 封装请求数据
         */
        $request_data = [
            'partnerid' => $this->partnerid,
            'version' => $this->version,
            'request' => $request,
            'xmldata' => $xml_data,
            'validation' => $this->validation,
        ];
        $xml = HttpHelper::request($method, $this->uri(), http_build_query($request_data))->getBody();
        return xml_to_obj($xml)->response;
    }

    /*****
     * 封装xml数据
     * @param array $data
     */
    private function build_xml($data){
        $xml_data = XMLHelper::xml_encode($data, false, $this->xml_root_name, $this->xml_item_name, '', $this->xml_id_name);
        return base64_encode($xml_data);
    }

    /****
     * 检查订单item信息
     * @param $data
     * @param string $item
     * @return bool
     */
    protected function check_item_info($data, $item = 'order')
    {
        if (!isset($data[$item])) {
            return "订单[$item]字段不能为空";
        } else {
            return false;
        }
    }

    /******
     * 生成唯一订单号
     * @return string
     */
    protected function create_serial_no(){
        $no = StringHelper::create_rand_string(6, '0123456789');
        return  'YD' . date('YmdHis') . $no;
    }

    /**
     * order_type
     * @return array|Json
     */
    public function order_type()
    {
        $type = [
            ['type' => 'common', 'name' => '普通'],
            ['type' => 'insured', 'name' => '保价'],
            ['type' => 'cod', 'name' => '代收货款'],
            ['type' => 'gss', 'name' => '国际件'],
            ['type' => 'gss_export', 'name' => '国际件出口'],
            ['type' => 'df', 'name' => '到付'],
            ['type' => 'present', 'name' => '礼品'],
        ];
        return json($type);
    }

}