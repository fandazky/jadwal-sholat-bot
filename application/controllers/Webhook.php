<?php defined('BASEPATH') OR exit('No direct script access allowed');

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Webhook extends CI_Controller {

    private $bot;
    private $events;
    private $signature; 
    private $user;
    private $parameters;

  	function __construct() {
    	parent::__construct();
    	$this->load->model('tebakkode_m');
        $this->config->load('api');
        $accessToken = $this->config->item('CHANNEL_ACCESS_TOKEN');
        $channelSecret = $this->config->item('CHANNEL_SECRET');
		$httpClient = new CurlHTTPClient($accessToken);
		$this->bot  = new LINEBot($httpClient, ['channelSecret' => $channelSecret]);
  	}

  	public function index() {
	    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	      	echo "Hello Coders!";
            $a = 2;
            echo 'tes '.($a + 1);
	      	header('HTTP/1.1 400 Only POST method allowed');
	      	exit;
	    } 

	    // get request
	    $body = file_get_contents('php://input');
	    $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
	    $this->events = json_decode($body, true);

	    foreach ($this->events['events'] as $event){
		   // skip group and room event
		   if(! isset($event['source']['userId'])) continue;

		   // get user data from database
		   $this->user = $this->tebakkode_m->getUser($event['source']['userId']);

		   // respond event
		   	if($event['type'] == 'message'){
		       	if(method_exists($this, $event['message']['type'].'Message')){
		       		$this->{$event['message']['type'].'Message'}($event);
		       	}
		   	} else {
		       if(method_exists($this, $event['type'].'Callback')){
		       		$this->{$event['type'].'Callback'}($event);
		       }
		   	}
		}

	    // log every event requests);
	    $this->tebakkode_m->log_events($this->signature, $body);
  	}

  	private function followCallback($event) {
	    $res = $this->bot->getProfile($event['source']['userId']);
	    if ($res->isSucceeded()){	    	
	        $profile = $res->getJSONDecodedBody();
	        // save user data
	        $this->tebakkode_m->saveUser($profile);
	        // send welcome message
	        $message = "Salam kenal, " . $profile['displayName'] . " (".$profile['userId'].")!\n";
	        $message .= "Silakan kirim pesan \"JADWAL\" untuk mengecek jadwal sholat.";
	        $textMessageBuilder = new TextMessageBuilder($message);
	        $this->bot->pushMessage($event['source']['userId'], $textMessageBuilder);
	        $stickerMessageBuilder = new StickerMessageBuilder(1, 3);
	        $this->bot->pushMessage($event['source']['userId'], $stickerMessageBuilder);
	    }

	}

	private function textMessage($event) {
        $userMessage = $event['message']['text'];
        if($this->user['number'] == 0) {
            if(strtolower($userMessage) == 'jadwal'){
                $this->tebakkode_m->setUserProgress($this->user['user_id'], 1);
                $this->sendQuestion($this->user['user_id'], 1);
            } else {
                $message = 'Silakan kirim pesan "MULAI" untuk memulai kuis.';
                $textMessageBuilder = new TextMessageBuilder($message);
                $this->bot->pushMessage($event['source']['userId'], $textMessageBuilder);
            }
        } else {
            $this->checkAnswer($userMessage);
        }

    }

    public function sendQuestion($user_id, $questionNum = 1) {
        $this->load->library('Restclient');
        switch ($questionNum) {
            case 1:
                $method_list = $this->restclient->get('http://ibacor.com/api/pray-times');
                $message = "Silahkan pilih metode perhitungan waktu :\n";
                if ($method_list['status'] == 'success'){
                    foreach ($method_list['method'] as $key => $value) {
                        $num = $value['value']+1;
                        $message .= $num.". ".$value['name']."\n";
                    }
                } else {
                    $message .= "tidak ada data";
                }
                $messageBuilder = new TextMessageBuilder($message);
                break;
            case 2:
                $message = "Masukkan kota waktu sholat\n";
                $messageBuilder = new TextMessageBuilder($message);
                break;
            case 3:
                $message = "Masukkan tanggal waktu sholat (format:dd-mm-yyyy)\n ";
                $messageBuilder = new TextMessageBuilder($message);
                break;
            case 4:
                // $options[0] = new MessageTemplateActionBuilder("WIB", "7");
                // $options[1] = new MessageTemplateActionBuilder("WITA", "8");
                // $options[2] = new MessageTemplateActionBuilder("WIT", "9");
                // $buttonTemplate = new ButtonTemplateBuilder("", "Silahkan pilih zona waktu", "", $options);
                // $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
                $message = "Silahkan pilih zona waktu.\n7 = WIB, 8 = WITA, 9 = WIT";
                $messageBuilder = new TextMessageBuilder($message);
                break;
            default:
                break;
        }
        
        
        $response = $this->bot->pushMessage($user_id, $messageBuilder);

    }

    private function checkAnswer($message){
        $this->load->library('session');
        switch ($this->user['number']) {
            case 1:
                $old_param = $this->tebakkode_m->getOldParams($this->user['user_id']);
                if ($old_param) {
                    $this->tebakkode_m->updateParamRequest($this->user['user_id'], 'method='.((int)$message - 1));
                } else {
                    $this->tebakkode_m->setParamRequest(array('user_id'=>$this->user['user_id'],'params'=>'method='.((int)$message - 1)));
                }
                break;
            case 2:
                $old_param = $this->tebakkode_m->getOldParams($this->user['user_id']);
                $city = strtolower(str_replace(" ", "-", $message));
                $this->tebakkode_m->updateParamRequest($this->user['user_id'], $old_param['params'].'&address='.$city);
                break;
            case 3:
                $raw_date = explode('-', $message);
                $old_param = $this->tebakkode_m->getOldParams($this->user['user_id']);
                $this->tebakkode_m->updateParamRequest($this->user['user_id'], $old_param['params'].'&day='.$raw_date[0].'&month='.$raw_date[1].'&year='.$raw_date[2]);
                break;
            case 4:
                $old_param = $this->tebakkode_m->getOldParams($this->user['user_id']);
                $this->tebakkode_m->updateParamRequest($this->user['user_id'], $old_param['params'].'&timezone='.$message);
                break;
            default:
                break;
        }

        if($this->user['number'] < 4) {
            $this->tebakkode_m->setUserProgress($this->user['user_id'], $this->user['number'] + 1);
            $this->sendQuestion($this->user['user_id'], $this->user['number'] + 1);
        } else {
            $message = "Jadwal sholat yang anda minta:\n";
            $this->load->library('Restclient');

            $api_params = $this->tebakkode_m->getOldParams($this->user['user_id']);
            $time_result = $this->restclient->get('http://ibacor.com/api/pray-times?'.$api_params['params']);
            $results = $time_result['result'];
            foreach ($results as $key => $value) {
                $message .= $key." : ".$value."\n";
            }
            
            $message .= "\nTerima kasih sudah menggunakan layanan bot Jadwal Sholat";
            $textMessageBuilder = new TextMessageBuilder($message);
            $this->bot->pushMessage($this->user['user_id'], $textMessageBuilder);
            $this->tebakkode_m->setUserProgress($this->user['user_id'], 0);
        }
    }
}