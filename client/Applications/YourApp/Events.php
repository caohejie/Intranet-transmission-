<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);
use \Workerman\Connection\AsyncTcpConnection;
use \GatewayWorker\Lib\Gateway;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接idGATEWAY_ADDR
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message)
   {
       global $dnsconfig;

       $dom=self::getallheaders($message);

       if(!isset($dnsconfig[$dom])){
            self::onClose();
       }

       $connection = new AsyncTcpConnection('tcp://'.$dnsconfig[$dom].':80');
       // 当连接建立成功时，发送http请求数据
       $connection->onConnect = function($connection)use($message)
       {
           $connection->send($message);
       };
       $connection->onMessage = function($connection, $http_buffer)use($client_id)
       {
           Gateway::sendToClient($client_id,$http_buffer);
       };
       $connection->onClose = function($connection_to_baidu)
       {

       };
       $connection->onError = function($connection, $code, $msg)
       {
           echo "Error code:$code msg:$msg\n";
       };
       $connection->connect();

   }
   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
       // 向所有人发送 
       GateWay::sendToAll("$client_id logout\r\n");
   }

    public static function getallheaders($message)
    {
        $arr= explode("\r\n",$message);

        foreach ($arr as $key=>$val){

            if(strpos($val,'Host:') !== false){
                $newarr=explode(" ",$val);

                return $newarr[1];
            }

        }

    }
}
