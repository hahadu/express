<?php


namespace Hahadu\Express\Builds;


class BuildMessage
{
    /*****
     * �ɹ���װ��ݵ�����Ϣ
     */
    static public function build_express_no($order_no,$express_no,$express_no_info,$code,$message){
        return compact('order_no','express_no','express_no_info','code','message');
    }

}