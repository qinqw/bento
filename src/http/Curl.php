<?php
/**
 * Curl类库封装 The Curl wrapper class library for HTTP requests
 * @文件名称: Curl.php
 * @author: Kevin
 * @Email: qinqiwei@hotmail.com
 * @Date: 2017-06-01
 */
namespace http;
/**
 * Curl类库封装
 *
 *      应用实例
 *      $url = "http://192.168.88.197:8080/api/jobs";
 *              
 *      $curl3 = new Curl("");
 *      $curl3 -> set_usrpwd('yjs','abc123');
 *      $curl3 -> get($url,"");
 *      $response = $curl3 -> exec();     
 *      $jobs = $response;
 *      
 *      注意get 方法不支持 params传递，url直接定义为 “http://host:post/path?params” 格式
 */
 
class Curl
{
	private $ch;  //Curl 实例
	private $flag_if_have_run; //是否已经之行 curl_exec
    
    /**
     * 构造函数
     * @access public
     * @param string $url 数据格式 'http://192.168.88.197:8080/api/jobs'
     * @return void
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */	
	public function __construct($url)
	{
		$this->ch = curl_init($url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER , 1 );		
	}
	
    /**
     * 关闭curl连接
     * @access public
     * @return void
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function close()
	{
		curl_close($this->ch);
	}
	
     /**
     * 析构函数
     * @access public
     * @return void
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */	
	public function __destruct()
	{
		$this->close();
	}
	
     /**
     * 设置超时
     * @access public
     * @param int $timeout
     * @return 
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */	
	public function set_time_out($timeout)
	{
		curl_setopt($this->ch, CURLOPT_TIMEOUT, intval($timeout));
		return $this;
	}
	
    /**
     * 设置重定向
     * @access public
     * @param int $referer  格式 URL
     * @return 
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function set_referer($referer)
	{
		if (!empty($referer))
			curl_setopt($this->ch, CURLOPT_REFERER , $referer);
		return $this;
	}
	
    /**
     * 从文件载入cookie
     * @access public
     * @param string $cookie_file  文件名
     * @return 
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function load_cookie($cookie_file)
	{
		curl_setopt($this->ch, CURLOPT_COOKIEFILE , $cookie_file);
		return $this;
	}
	
    /**
     * 保存cookie到文件
     * @access public
     * @param string $cookie_file  文件名
     * @return 
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function save_cookie($cookie_file="")
	{
		//设置缓存文件，例如a.txt
		if(empty($cookie_file))
			$cookie_file = tempnam('./', 'cookie');
		curl_setopt($this->ch, CURLOPT_COOKIEJAR , $cookie_file);
		return $this;
	}
	
    /**
     * 执行curl请求
     * @access public
     * @return 
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function exec ()
	{
		$str = curl_exec($this->ch);
		$this->flag_if_have_run = true;
		return $str;
	}
        
    public function get($url,$headers="")
    {
        $this->requests($url,"GET","",$headers);
    }
        
    public function post($url,$params,$headers="")
    {
        $this->requests($url,"POST",$params,$headers);
    }
    
    public function put($url,$params,$headers="")
    {
        $this->requests($url,"PUT",$params,$headers);
    }
    
    public function delete($url,$params,$headers="")
    {
        $this->requests($url,"DELETE",$params,$headers);
    }
    
    /**
     * 设置Crul请求信息
     * @access public
     * @param string $URL 地址
     * @param string $type 请求类型 GET,POST,PUT,DELETE
     * @param string $params 请求参数 jsonstr 或者 urlparamsstr
     * @param array $header http请求头
     * @return curl $this->ch
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
    public function requests($URL,$type,$params,$headers){  
        //$ch = curl_init();
        $timeout = 25; 
        if($URL != "")
        {
            curl_setopt ($this->ch, CURLOPT_URL, $URL); //发贴地址  
        }
        if($headers!=""){  
            curl_setopt ($this->ch, CURLOPT_HTTPHEADER, $headers);  
        }else {  
            curl_setopt ($this->ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));  
        }  
        curl_setopt ($this->ch, CURLOPT_RETURNTRANSFER, 1);  
        curl_setopt ($this->ch, CURLOPT_CONNECTTIMEOUT, $timeout);  
        curl_setopt ($this->ch, CURLOPT_SSL_VERIFYPEER, false );
        switch ($type){  
            case "GET" : curl_setopt($this->ch, CURLOPT_HTTPGET, true);
                         break;  
            case "POST": curl_setopt($this->ch, CURLOPT_POST,true);   
                         curl_setopt($this->ch, CURLOPT_POSTFIELDS,$params);
                         break;  
            case "PUT" : curl_setopt ($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");   
                         curl_setopt($this->ch, CURLOPT_POSTFIELDS,$params);
                         break;  
            case "DELETE":curl_setopt ($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");   
                          curl_setopt($this->ch, CURLOPT_POSTFIELDS,$params);
                          break;  
        }  
        return $this;
    }
	
    /**
     * 获取http请求返回头
     * @access public
     * @return array httpheader
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function get_info()
	{
		if($this->flag_if_have_run == true )
			return curl_getinfo($this->ch);
		else 
			throw new Exception("exec first!");
	}
    
    /**
     * 获取 http 请求返回 状态代码
     * @access public
     * @return string http代码
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
    public function get_http_code()
	{
		if($this->flag_if_have_run == true )
			return curl_getinfo($this->ch,CURLINFO_HTTP_CODE);
		else 
			throw new Exception("exec first!");
	}
    
    /**
     * Auth_Basic 认证方式 设置用户名,密码
     * @access public
     * @param string $username 用户名
     * @param string $password 密码
     * @return string http代码
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
    public function set_usrpwd($username,$password)
    {
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_USERPWD, $username.":".$password);
		return $this;
    }
	
    /**
     * 设置代理服务器
     * @access public
     * @param string $proxy 代理数据格式 '68.119.83.81:27977'
     * @return $this
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function set_proxy($proxy)
	{
		curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		curl_setopt($this->ch, CURLOPT_PROXY,$proxy);
		return $this;
	}
	
    /**
     * 使用代理服务器时，设置请求来源地址IP
     * @access public
     * @param string $ip IP数据格式 '68.119.83.81'
     * @return string ip
     *
     * @author: Kevin <qinqiwei@hotmail.com>
     */
	public function set_ip($ip)
	{
		if(!empty($ip))
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("X-FORWARDED-FOR:".$ip, "CLIENT-IP:".$ip));
		return $ip;
	}
	
}
