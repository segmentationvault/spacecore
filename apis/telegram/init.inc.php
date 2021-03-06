<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class API_TELEGRAM
{
    private $object_broker;
    private $classname;


    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->apis[] = 'api_telegram';
        debug_log($this->classname . ": starting up");
    }


    public function __destruct()
    {

    }
    
    private function call_curl($method, $params)
    {
        global $config;

        $ch = curl_init($config['api_endpoint'] . $method);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function send_message($target, $message, $inlineKB=null)
    {
        // set parameters
        $params = [
            'chat_id'=>$target,
            'text'=>$message,
            'parse_mode'=>'HTML'
        ];

        if($inlineKB != null)
        {
            $kb = array();

            foreach($inlineKB as $r)
            {
                $row = array();

                foreach($r as $text => $command)
                {
                    $button = array();
                    $button['text'] = $text;
                    $button['callback_data'] = $command;
                    array_push($row, $button);
                }
                array_push($kb, $row);
            }
            $params['reply_markup'] = json_encode( ["inline_keyboard" => $kb ] );
            debug_log($this->classname . ":reply_markup=".$params['reply_markup']);
        }

        // send request
        $result = $this->call_curl("sendMessage", $params);
        debug_log($this->classname . ":sendMessage: $result");
        
        $result_arr = json_decode($result, true);
        if($result_arr == null)
        {
            debug_log($this->classname . ":sendMessage: response was null");
            return false;
        }
        if($result_arr['ok'] == 'true')
        {
            if(
                array_key_exists("result", $result_arr) &&
                array_key_exists("message_id", $result_arr["result"])
            ){
                return $result_arr['result']['message_id'];
            }
        }else{
            return false;
        }
    }


    public function delete_message($chat_id, $msg_id)
    {
        // set parameters
        $params = [
            'chat_id'=>$chat_id,
            'message_id'=>$msg_id
        ];

        // send request
        $result = $this->call_curl("deleteMessage", $params);
        debug_log($this->classname . ":deleteMessage: $result");
    }


    public function send_coordinate($chat_id, $lat, $lon)
    {
        // set parameters
        $params = [
            'chat_id'=>$chat_id,
            'latitude'=>$lat,
            'longitude'=>$lon,
        ];

        // send request
        $result = $this->call_curl("sendLocation", $params);
        debug_log($this->classname . ":sendLocation: $result");
    }

    public function download_resource($file_id, $destination)
    {
        global $config;

        // set parameters
        $params = [
            'file_id'=>$file_id
        ];

        // send request
        $result = $this->call_curl("getFile", $params);
        $result_arr = json_decode($result, true);

        if($result_arr['ok'] == 'true')
        {
            $resource_location = $config['download_endpoint'] . $result_arr['result']['file_path'];
            $resource_extension = pathinfo($resource_location, PATHINFO_EXTENSION);

            if(in_array($resource_extension, $config['allowed_photo_extensions']))
            {
                file_put_contents($destination . '.' . $resource_extension, file_get_contents($resource_location));
                debug_log($this->classname . ":getFile: $file_id saved to $destination . '.' . $resource_extension");
            }
            else
            {
                error_log($this->classname . ":getFile: failed (extension $resource_extension not whitelisted, file_id $file_id)");
            }
        }
        else
        {
            error_log($this->classname . ":getFile: failed (whoops). Trace: $result");
        }

    }
    
}

?>
