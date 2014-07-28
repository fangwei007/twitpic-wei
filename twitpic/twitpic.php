<?php
//using this code one can easily upload pictures to TwitPic using TwitPic API and Abraham Willium's Twitter OAuth class
/*
 * User has successfully authenticated with Twitter. Access tokens saved to session and DB.
 */

class Twitpic {

    /* User tokens, will get it from session */
    public $access_token;
    
    /* Oauth token and secret, included in access token */
    public $oauth_token;
    public $oauth_secret;
    
    /* Can use it directly post stuff on twitter */
    public $OAuth_connection;
    
    /* Some variables for authentication */
    public $timestamp;
    public $nonce;
    public $consumer_key;
    public $consumer_secret;
    
    /* Twitpic api init variables */
    public $twitpic_url = "http://api.twitpic.com/2/upload.json";
    public $args = array();
    
    /* The header information for TwitPic */
    public $header_url = "https://api.twitter.com/1.1/account/verify_credentials.json";
    public $headers;

    public function __construct() {
        session_start();
        require_once('twitteroauth/twitteroauth.php');
        require_once('config.php');
        
        /* Initialize authenticate variables */
        $this->consumer_key = CONSUMER_KEY;
        $this->consumer_secret = CONSUMER_SECRET;
        $this->timestamp = time();
        $this->nonce = md5(uniqid(rand(), TRUE));
        
        /* This part is used to set uploading args */
        // if (isset($_POST) && $_FILES['media']['err'] == 0) {
        //     $message = $_POST['message'];
        //     $media = '@./images/upload/' . $_FILES['media']['name'];
        //     $this->args = array(
        //         'key' => 'dbaffc71aaf0fef6dbc6d1a7a3188dfd',
        //         'message' => $message,
        //         'media' => $media
        //     );
        // }
        // var_dump($_SESSION);
        $this->args = array(
                'key' => 'dbaffc71aaf0fef6dbc6d1a7a3188dfd',
                'message' => "".stripslashes($_SESSION['message'])." ",
                'media' => '@./images/upload/'.$_SESSION['media']
            );
    }

    public function verifyAccessToken() {
        
        /* If access tokens are not available redirect to connect page. */
        if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
            header('Location: ./clearsessions.php');
        }
        $this->access_token = $_SESSION['access_token'];
        $this->oauth_token = $_SESSION['access_token']['oauth_token'];
        $this->oauth_secret = $_SESSION['access_token']['oauth_token_secret'];
    }

    protected function createOauth() {
        /* Create a TwitterOauth object with consumer/user tokens. */
        return $this->OAuth_connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret, $this->access_token['oauth_token'], $this->access_token['oauth_token_secret']);
    }

    protected function generateSignature() {
//        $this->createOauth();
        
        /* Parameters for generating signature. Remember the signature is sent to twitpic, not to Twitter */
        $oconsumer_key = "oauth_consumer_key=" . $this->consumer_key;
        $ononce = "oauth_nonce=" . $this->nonce;
        $osigmethod = "oauth_signature_method=HMAC-SHA1";
        $otimestamp = "oauth_timestamp=" . $this->timestamp;
        $otoken = "oauth_token=" . $this->oauth_token;
        $oversion = "oauth_version=1.0";

        /*
         * Creating a single base string for creating the signature. 
         * this can be done with arrays but a small error will give you a 401 header rejected by Twitter error, 
         * this can lessen the error. don't change anything. 
         * They are arranged in the alphabetical order as they should be, 
         * using arrays and kstort might work, 
         * but there should not be any spaces between the parameters and their values ex : oauth_token= 1234 not allowed
         */

        $singlestring = $oconsumer_key . "&" . $ononce . "&" . $osigmethod . "&" . $otimestamp . "&" . $otoken . "&" . $oversion;

        /* Encoding the urls */
        $encsbs = rawurlencode($singlestring);
        $encurl = rawurlencode($this->header_url);
        $http_method = "GET";

        /* Single base string for the signature text is ready */
        $content = $http_method . "&" . $encurl . "&" . $encsbs;
        $key = $this->consumer_secret . '&' . $this->oauth_secret;

        /* Generate the signature */
        $signature = urlencode(base64_encode(hash_hmac('sha1', $content, $key, TRUE)));

        /* Putting the signature and everything we need to send to the header */
        $this->headers = <<<EOF
OAuth realm="http://api.twitter.com/", oauth_consumer_key="$this->consumer_key", oauth_signature_method="HMAC-SHA1", oauth_token="$this->oauth_token", oauth_timestamp="$this->timestamp", oauth_nonce="$this->nonce", oauth_version="1.0", oauth_signature="$signature"
EOF;
    }

    protected function sendRequest() {
        
        /* Using curl the request is sent to twitpic not twitter */
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->twitpic_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_FAILONERROR, FALSE);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'X-Verify-Credentials-Authorization: ' . $this->headers,
            'X-Auth-Service-Provider: ' . $this->header_url
        ));

        /* We post your $args array with you api key, message, and media... */
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->args);

        $response = curl_exec($curl);
        if (!$response) {
            $response = curl_error($curl);
            return 0;
        }

        curl_close($curl);
        return $response;
    }

    protected function toTwitter() {
        
        /* Post stuff to Twitter */
        $response = $this->sendRequest();
        if ($response) {
            $json_data = json_decode($response, TRUE);
            $text = urlencode(stripslashes($json_data['url']) . " " . $json_data['text'] . "");
            header("Location: http://twitter.com/intent/tweet?url=&text=" . $text);
        }
        /*
         * If the twitpic sends JSON data as the twitpic API say then it's all good. 
         * 401 header rejected error means there is something wrong with the signature. 
         */
    }
    
    public function sendTweet() {
        $this->verifyAccessToken();
        $this->generateSignature();
        $this->toTwitter();
    }

}
$twitpic = new Twitpic();
$twitpic->sendTweet();

//$twitpic->verifyAccessToken();
//
//$twitpic->generateSignature();
//
////$twitpic->sendRequest();//this will only upload image to twitpic, by calling toTwitter, image will be posted to twitter
//
//$twitpic->toTwitter();

