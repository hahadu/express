<?php


namespace Hahadu\Express\Builds;


class BuildMessage
{
    /*****
     * 成功封装快递单号信息
     */
    static public function build_express_no($order_no,$express_no,$express_no_info,$code,$message){
        return compact('order_no','express_no','express_no_info','code','message');
    }

}