<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// this seems to be not the CI way, but...
require_once (APPPATH.'/libraries/Rat.php');

class RatExtended extends Rat
{
    public function __construct()
    {
        parent::__construct()
    }
    
    /*
     * extending the log function: adding $content array and processing it // donato
     */
    public function log($message, $code = 0, $user_id = 0, $content = array()) //donato
    {
        $session_user_id = $this->ci->config->item('session_user_id','rat');
        if(($user_id==0) && !empty($session_user_id))
        {
            $user_id = isset($_SESSION[$session_user_id]) ? $_SESSION[$session_user_id] : '0';
        }
        
        $content_json =  substr(json_encode($content), 21844); //donato
        
        if($this->_set_message($message,$user_id,$code,$content_json)) //donato
        {
            return TRUE;
        }
        else
        {
            show_error('That rat... you must pop it... or repair the library...');
        }
        return FALSE;
    }
 
    /*
     * retrieve something
     */
    public function get_log($user_id = NULL, $code = NULL, $content_json = NULL,  $date = NULL, $order_by = NULL, $limit = NULL)
    {
        return $this->_get_messages($user_id, $code, $content_json, $date, $order_by, $limit); //donato
    }
    
    private function _set_message($message,$user_id,$code)
    {
        if($this->_store_in == 'database')
        {
            $date_time = date('Y-m-d H:i:s');
            $insert_data = array(
                'user_id' => $user_id,
                'date_time' => $date_time,
                'code' => $code,
                'message' => $message,
                'content_json' => $content_json // donato
            );
            if($this->ci->rat_model->set_message($insert_data))
            {
                return TRUE;
            }
        }
        else {
            $date = date('Y-m-d'); 
            $date_time = date('Y-m-d H:i:s');
            $file = $this->_store_in.'/log-' . $user_id . '-' . $date . '.php';
            $log_message = $date_time . ' *-* ' . $code . ' *-* ' . $message . ' *-* ' . $content_json . "\r\n"; //donato
            if (!file_exists($file)) {
                // File doesn't exists so we need to first write it.
                $log_message = "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>\r\n\r\n" . $log_message;
            }
            $log = fopen($file, "a");
            if (fwrite($log, $log_message))
            {
                fclose($log);
                return TRUE;
            }
            else
            {
                show_error('Couldn\'t write on the file');
            }
        }
        return FALSE;
    }
    
    private function _get_messages($user_id = NULL, $code = NULL, $date = NULL, $order_by = NULL, $limit = NULL)
    {
        if($this->_store_in == 'database')
        {
            $where = array();
            if(isset($user_id)) $where['user_id'] = $user_id;
            if(isset($code)) $where['code'] = $code;
            if(isset($date))
            {
                $where['date_time >='] = $date.' 00:00:00';
                $where['date_time <='] = $date.' 23:59:59';
            }
            if(!isset($order_by)) $order_by = 'date_time DESC';
            return $this->ci->rat_model->get_messages($where, $order_by, $limit);
        }
        else
        {
            $user_id = (isset($user_id)) ? $user_id : '*';
            $date = (isset($date)) ? $date : '*';
            $files = $this->_store_in.'/log-' . $user_id . '-' . $date . '.php';
            $messages = array();
            foreach (glob($files) as $filename)
            {
                $log = file_get_contents($filename);
                $lines = explode("\r\n",$log);
                for ($k=2; $k<count($lines); $k++) {
                    if(strlen($lines[$k])>0)
                    {
                        $line = explode('*-*',$lines[$k]);
                        $date_time = $line[0];
                        $code = $line[1];
                        $message = $line[2];
                        $content_json = $line[3]; // donato
                        $messages[] = array('user_id'=>$user_id,'date_time'=>$date_time,'code'=>$code,'message'=>$message,'content_json'=>$content_json); //donato
                    }
                }
            }
            return json_decode(json_encode($messages)); 
        }
    }
}
