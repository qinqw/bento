<?php
/**
 * Amqp消息代理人类 
 * @文件名称: Broker.php
 * @author: Kevin
 * @Email: qinqiwei@hotmail.com
 * @Date: 2016-07-10
 */

namespace Qinqw\Bento\Amqp;
/*
 * amqp协议操作类，可以访问rabbitMQ
 * 需先安装php_amqp扩展 librabbitmq amqp.1.7.1
 */
 //Consumer Producer
 //Receiver Sender
class Broker
{
    public $configs = array();
    //交换机名称
    public $exchange_name = 'qinqw.default';
    //队列名称
    public $queue_name = 'qinqw.pub';
    //路由名称
    public $route_key = 'rk_default_qinqw';

    private $route_key_prefix = "rk_qinqw_";

    /*
     * 持久化，默认True
     */
    public $durable = True;
    /*
     * 自动删除
     * exchange is deleted when all queues have finished using it
     * queue is deleted when last consumer unsubscribes
     * 
     */
    public $autodelete = False;
    /*
     * 镜像
     * 镜像队列，打开后消息会在节点之间复制，有master和slave的概念
     */
    public $mirror = False;
    
    private $_conn = Null;
    private $_exchange = Null;
    private $_channel = Null;
    private $_queue = Null;

    /*
     * @configs array('host'=>$host,'port'=>5672,'username'=>$username,'password'=>$password,'vhost'=>'/')
     */

    public function __construct($configs = array(), $queue_name = null, $exchange_name = null, $route_key = null)
    {
        $this->setConfigs($configs);
        if(!(is_null($queue_name)||trim($queue_name)==''))
        {
            $this->queue_name = $queue_name;
        }
        if(!(is_null($exchange_name)||trim($exchange_name)==''))
        {
            $this->exchange_name = $exchange_name;
        }
        if(!(is_null($route_key)||trim($route_key)==''))
        {
            $this->route_key = $route_key;
        }else
        {
            $this->route_key = $this->route_key_prefix.$this->queue_name;
        }
    }
    
    private function setConfigs($configs)
    {
        if (!is_array($configs)) 
        {
            throw new \Exception('configs is not array');
        }
        if (!($configs['host'] && $configs['port'] && $configs['username'] && $configs['password']))
        {
            throw new \Exception('configs is empty');
        }
        if (empty($configs['vhost']))
        {
            $configs['vhost'] = '/';
        }
        $configs['login'] = $configs['username'];
        unset($configs['username']);
        $this->configs = $configs;
    }

    /*
     * 设置是否持久化，默认为True
     */
    public function setDurable($durable)
    {
        $this->durable = $durable;
    }

    /*
     * 设置是否自动删除
     */
    public function setAutoDelete($autodelete)
    {
        $this->autodelete = $autodelete;
    }
    /*
     * 设置是否镜像
     */
    public function setMirror($mirror)
    {
        $this->mirror = $mirror;
    }

    /*
     * 打开amqp连接
     */
    private function open()
    {
        if (!$this->_conn) 
        {
            try 
            {
                $this->_conn = new \AMQPConnection($this->configs);
                $this->_conn->connect();
                $this->initConnection();
            } 
            catch (\AMQPConnectionException $ex) 
            {
                throw new \Exception('cannot connection rabbitmq',500);
            }
        }
    }

    /*
     * rabbitmq连接不变
     * 重置交换机，队列，路由等配置
     */
    public function reset($exchange_name, $queue_name, $route_key) 
    {
        $this->exchange_name = $exchange_name;
        $this->queue_name = $queue_name;
        $this->route_key = $route_key;
        $this->initConnection();
    }

    /*
     * 初始化rabbit连接的相关配置
     */
    private function initConnection() 
    {
        if (empty($this->exchange_name) || empty($this->queue_name) || empty($this->route_key)) 
        {
            throw new \Exception('rabbitmq exchange_name or queue_name or route_key is empty',500);
        }
        $this->_channel = new \AMQPChannel($this->_conn);
        $this->_exchange = new \AMQPExchange($this->_channel);
        $this->_exchange->setName($this->exchange_name);

        $this->_exchange->setType(AMQP_EX_TYPE_DIRECT);
        if ($this->durable)
        {
            $this->_exchange->setFlags(AMQP_DURABLE);
        }
        if ($this->autodelete)
        {
            $this->_exchange->setFlags(AMQP_AUTODELETE);
        }
        $this->_exchange->declareExchange();

        $this->_queue = new \AMQPQueue($this->_channel);
        $this->_queue->setName($this->queue_name);
        if ($this->durable)
        {
            $this->_queue->setFlags(AMQP_DURABLE);
        } 
        if ($this->autodelete)
        {
            $this->_queue->setFlags(AMQP_AUTODELETE);
        }
        if ($this->mirror)
        {
            $this->_queue->setArgument('x-ha-policy', 'all');
        }
        $this->_queue->declareQueue();
        
        $this->_queue->bind($this->exchange_name, $this->route_key);
    }

    public function close()
    {
        if ($this->_conn)
        {
            $this->_conn->disconnect();
        }
    }
    
    public function __sleep()
    {
        $this->close();
        return array_keys(get_object_vars($this));
    }

    public function __destruct() 
    {
        $this->close();
    }
    
    /*
     * 生产者发送消息
     */
    public function publish($msg) 
    {
        $this->open();
        if(is_array($msg)||is_object($msg))
        {
            $msg = json_encode($msg);
        }else
        {
            $msg = trim(strval($msg));
        }
        return $this->_exchange->publish($msg, $this->route_key);
    }

    /*
     * 消费者
     * $fun_name = array($classobj,$function) or function name string
     * $autoack 是否自动应答
     * 
     * function processMessage($envelope, $queue) {
            $msg = $envelope->getBody(); 
            echo $msg."\n"; //处理消息
            $queue->ack($envelope->getDeliveryTag());//手动应答
        }
     */
    public function consume($callback, $autoack = true){
        $this->open();
        if (!$callback || !$this->_queue)
        {
             return false;
        }
        while(true)
        {
            if ($autoack) 
            {
                $this->_queue->consume($callback, AMQP_AUTOACK);
            }
            else
            {
                $this->_queue->consume($callback);
            } 
        }
    }

    /*
     * 获取消息（单条）
     */
    public function get($autoack=true)
    {
        $this->open();
        if($autoack==true)
        {
            $envelope = $this->_queue->get(AMQP_AUTOACK);
            //$this->_queue->ack($envelope->getDeliveryTag());
        }
        else
        {
            $envelope = $this->_queue->get(AMQP_NOPARAM);
        }
        return $envelope;
    }

    /*
     * 手动应答
     * $delivery_tag = $envelope->getDeliveryTag();
     */
    public function ack($delivery_tag)
    {
        return $this->_queue->ack($delivery_tag);
    }

}
