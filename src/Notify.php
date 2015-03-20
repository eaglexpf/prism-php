<?php
class Notify extends Oauth {

    var $last_buf;
    var $websocket;
    var $messages = array();

    const actionPublish = 0x01;
    const actionConsume = 0x02;
    const actionAck     = 0x03;

    const TextFrame     = 0x01;
    const BinaryFrame   = 0x02;
    const CloseFrame    = 0x08;
    const PingFrame     = 0x09;
    const PongFrame     = 0x09;

    public function connect () {

        $headers = array(
            'Upgrade'=>'websocket',
            'Sec-Websocket-Key' => base64_encode(time()),
            'Sec-WebSocket-Version' => 13,
            'Sec-WebSocket-Protocol' => 'chat',
            'Origin' => 'http://192.168.51.50:8080/api/platform/notify',
            'Connection'=>'Upgrade'
        );

        $this->setRequester('socket');
        $this->websocket = $this->get('/platform/notify', '', $headers);

        $header1 = fgets($this->websocket, 128);
        $header2 = fgets($this->websocket, 128);
        $header3 = fgets($this->websocket, 128);
        $header4 = fgets($this->websocket, 128);

    }

    public function publish($routing_key, $message, $content_type = "text/plain"){

        $size_routing_key   = strlen($routing_key);
        $size_message       = strlen($message);
        $size_content_type  = strlen($content_type);

        $data = pack(
            "na*Na*na*",
            $size_routing_key,
            $routing_key,
            $size_message,
            $message,
            $size_content_type,
            $content_type
        );

        $r = fwrite($this->websocket, $this->encode( self::BinaryFrame, pack("ca*", self::actionPublish , $data) ) );

        if ($r)
            return true;
        else
            return $r;

    }


    public function consume () {

        // 发请求
        fwrite($this->websocket, $this->encode( self::BinaryFrame, pack("ca*", self::actionConsume , '') ) );

        // 获取结果
        while (!isset($this->messages[0])){
            $this->recv_message();
        }

        return $this->messages[0];

    }

    public function ack ($tag_id) {


        $r = fwrite($this->websocket, $this->encode( self::BinaryFrame, pack("ca*", self::actionAck, $tag_id) ) );

        if ($r)
            return true;
        else
            return $r;

    }






    private function encode($type, $data='') {

        $b1 = 0x80 | ($type & 0x0f);

        $length = strlen($data);

        if($length <= 125)
            $header = pack('CC', $b1, 128 + $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 128 + 126, $length);
        elseif($length >= 65536)
            $header = pack('CCN', $b1, 128 + 127, $length);

        $key      = 0;
        $key      = pack("N", rand(0, pow(255, 4) - 1));
        $header  .= $key;

        return $header.$this->rotMask($data, $key);
    }

    private function rotMask($data, $key, $offset = 0) {
        $res = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $j = ($i + $offset) % 4;
            $res .= chr(ord($data[$i]) ^ ord($key[$j]));
        }

        return $res;
    }

    private function recv_message(){
        $raw = fread($this->websocket, 8192);

        $raw = $this->last_buf . $raw;
        $this->last_buf = '';
        $i = 0;

        while($raw){
            $i ++;
            $len = ord($raw[1]) & ~128;
            $data = substr($raw, 2);

            if ($len == 126) {
                $arr = unpack("n", $data);
                $len = array_pop($arr);
                $data = substr($data, 2);
            } elseif ($len == 127) {
                list(, $h, $l) = unpack('N2', $data);
                $len = ($l + ($h * 0x0100000000));
                $data = substr($data, 8);
            }

            if(strlen($data)>=$len){
                array_push($this->messages, substr($data, 0, $len));
                $raw = substr($data, $len);
            }else{
                $this->last_buf = $raw;
                return $i;
            }
        }
        return $i;
    }


}