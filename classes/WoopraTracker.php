<?php
/**
 * 2006-2020 THECON SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    THECON SRL <contact@thecon.ro>
 * @copyright 2006-2020 THECON SRL
 * @license   Commercial
 */

class WoopraTracker
{
    private static $SDK_ID = "php";
    /**
    * Default configuration.
    * KEYS:
    *
    * domain (string) - Website hostname as added to Woopra
    * cookie_name (string) - Name of the cookie used to identify the visitor
    * cookie_domain (string) - Domain scope of the Woopra cookie
    * cookie_path (string) - Directory scope of the Woopra cookie
    * ping (boolean) - Ping woopra servers to ensure that the visitor is still on the webpage?
    * ping_interval (integer) - Time interval in milliseconds between each ping
    * idle_timeout (integer) - Idle time after which the user is considered offline
    * download_tracking (boolean) - Track downloads on the web page
    * outgoing_tracking (boolean) - Track external links clicks on the web page
    * download_pause (integer) - Time in millisecond to pause the browser to ensure that the event is tracked when visitor clicks on a download url
    * outgoing_pause (integer) - Time in millisecond to pause the browser to ensure that the event is tracked when visitor clicks on an outgoing url
    * ignore_query_url (boolean) - Ignores the query part of the url when the standard pageviews tracking function track()
    * hide_campaign (boolean) - Enabling this option will remove campaign properties from the URL when they’re captured (using HTML5 pushState)
    * ip_address (string) - the IP address of the user viewing the page. If back-end processing, always set this manually.
    * cookie_value (string) - the value of "wooTracker" if it has been set.
    * @var array
    */
    private static $default_config = array(
        "domain" => "",
        "cookie_name" => "wooTracker",
        "cookie_domain" => "",
        "cookie_path" => "/",
        "ping" => true,
        "ping_interval" => 12000,
        "idle_timeout" => 300000,
        "download_tracking" => true,
        "outgoing_tracking" => true,
        "download_pause" => 200,
        "outgoing_pause" => 400,
        "ignore_query_url" => true,
        "hide_campaign" => false,
        "ip_address" => "",
        "cookie_value" => "",
        "app" => ""
    );

    /**
    * Custom configuration stack.
    * If the user has set up custom configuration, store it in this array. It will be sent when the tracker is ready.
    * @var array
    */
    private $custom_config;

    /**
    * Current configuration
    * Default configuration array, updated by Manual configurations.
    * @var array
    */
    public $current_config;

    /**
    * User array.
    * If the user has been identified, store his information in this array
    * KEYS:
    * email (string) – Which displays the visitor’s email address and it will be used as a unique identifier instead of cookies.
    * name (string) – Which displays the visitor’s full name
    * company (string) – Which displays the company name or account of your customer
    * avatar (string) – Which is a URL link to a visitor avatar
    * other (string) - You can define any attribute you like and have that detail passed from within the visitor live stream data when viewing Woopra
    * @var array
    */
    private $user;

    /**
    * Has the latest information on the user been sent to woopra?
    * @var boolean
    */
    private $user_up_to_date;

    /**
     * Woopra Analytics
     */
    public function __construct($config_params = null)
    {
        //Current configuration is Default
        $this->current_config = WoopraTracker::$default_config;

        //Set the default IP
        $this->current_config["ip_address"] = $this->getClientIp();

        //Set the domain name and the cookie_domain
        $this->current_config["domain"] = $_SERVER["HTTP_HOST"];
        $this->current_config["cookie_domain"] = $_SERVER["HTTP_HOST"];

        //configure app ID
        $this->current_config["app"] = WoopraTracker::$SDK_ID;
        $this->custom_config = array("app" => WoopraTracker::$SDK_ID);

        //If configuration array was passed, configure Woopra
        if (isset($config_params)) {
            $this->config($config_params);
        }

        //Get cookie or generate a random one
        if (!$this->current_config["cookie_value"] = $this->getCookieValue()) {
            $this->current_config["cookie_value"] = WoopraTracker::randomString();
        }

        //We don't have any info on the user yet, so he is up to date by default.
        $this->user_up_to_date = true;
    }

    public function getCookieValue()
    {
        $headerCookies = explode('; ', getallheaders()['Cookie']);
        foreach ($headerCookies as $item) {
            $cookie = explode('=', $item);
            if ($cookie[0] == $this->current_config["cookie_name"]) {
                return $cookie[1];
            }
        }
        return false;
    }

    /**
     * Random Cookie generator in case the user doesn't have a cookie yet. Better to use a hash of the email.
     * @param none
     * @return string
     */
    private static function randomString()
    {
        $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $randstring = "";
        for ($i = 0; $i < 12; $i++) {
            $randstring .= $characters[rand(0, Tools::strlen($characters)-1)];
        }
        return $randstring;
    }



    /**
     * Prepares the http request and sends it.
     */
    private function woopraHttpRequest($is_tracking, $event = null)
    {
        $base_url = "http://www.woopra.com/track/";

        //Config params
        $config_params = "?host=" . urlencode($this->current_config["domain"]);
        $config_params .= "&cookie=" . urlencode($this->current_config["cookie_value"]);
        $config_params .= "&ip=" . urlencode($this->current_config["ip_address"]);
        $config_params .= "&timeout=" . urlencode($this->current_config["idle_timeout"]);

        //User params
        $user_params = "";
        if (isset($this->user)) {
            foreach ($this->user as $option => $value) {
                if (! (empty($option) || empty($value))) {
                    $user_params .= "&cv_" . urlencode($option) . "=" . urlencode($value);
                }
            }
        }

        //Just identifying
        if (! $is_tracking) {
            $url = $base_url . "identify/" . $config_params . $user_params . "&app=" . $this->current_config["app"];

        //Tracking
        } else {
            $event_params = "";
            if ($event != null) {
                $event_params .= "&ce_name=" . urlencode($event[0]);
                foreach ($event[1] as $option => $value) {
                    if (! (empty($option) || empty($value))) {
                        $event_params .= "&ce_" . urlencode($option) . "=" . urlencode($value);
                        //also add referrer without prefix for woopra to pick up
                        if ($option == "referer" || $option == "referrer") {
                            $event_params .= "&referer=" . urlencode($value);
                        }
                    }
                }
            } else {
                $event_params .= "&ce_name=pv&ce_url=" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            }
            $url = $base_url . "ce/" . $config_params . $user_params . $event_params . "&app=" . $this->current_config["app"];
        }

        //Send the request
        if (function_exists('curl_version')) {
            $this->getData($url);
        } else {
            $opts = array(
                'http'=>array(
                    'method'=>"GET",
                    'header'=>"User-Agent: ".$_SERVER['HTTP_USER_AGENT']
                )
            );
            $context = stream_context_create($opts);
            Tools::file_get_contents($url, false, $context);
        }
    }

    /**
    * Configures Woopra
    * @param array
    * @return WoopraTracker object
    */
    public function config($args)
    {
        if (! isset($this->custom_config)) {
            $this->custom_config = array();
        }
        foreach ($args as $option => $value) {
            if (array_key_exists($option, WoopraTracker::$default_config)) {
                if (gettype($value) == gettype(WoopraTracker::$default_config[$option])) {
                    if ($option != "ip_address" && $option != "cookie_value") {
                        $this->custom_config[$option] = $value;
                    }
                    $this->current_config[$option] = $value;
                    //If the user is customizing the name of the cookie, check again if the user already has one.
                    if ($option == "cookie_name") {
                        if ($this->getCookieValue()) {
                            $this->current_config["cookie_value"] = $this->getCookieValue();
                        }
                    }
                } else {
                    trigger_error("Wrong value type in configuration array for parameter ".$option.". Recieved ".gettype($value).", expected ".gettype(WoopraTracker::$default_config[$option]).".");
                }
            } else {
                trigger_error("Unexpected parameter in configuration array: ".$option.".");
            }
        }
        return $this;
    }

    /**
    * Identifies User
    * @param array
    * @return WoopraTracker object
    */
    public function identify($identified_user, $override = false)
    {
        if (!empty($identified_user)) {
            $this->user = $identified_user;
            $this->user_up_to_date = false;

            if (isset($identified_user["email"]) && ! empty($identified_user["email"])) {
                if ($override || !$this->getCookieValue()) {
                    $this->current_config["cookie_value"] = crc32($identified_user["email"]);
                }
            }
            return $this;
        }
    }

    /**
    * Tracks Custom Event. If no parameters are specified, will simply track pageview.
    */
    public function track($event = null, $args = array())
    {
        $http_event = null;
        if ($event != null) {
            $http_event = array($event, $args);
        }
        $this->woopraHttpRequest(true, $http_event);
        return true;
    }

    public function push()
    {
        $this->woopraHttpRequest(false);
        $this->user_up_to_date = true;
        return true;
    }

    public function setWoopraCookie()
    {
        setcookie($this->current_config["cookie_name"], $this->current_config["cookie_value"], time()+(60*60*24*365*2), $this->current_config["cookie_path"], $this->current_config["cookie_domain"]);
    }

    /**
    * Retrieves the user's IP address
    */
    private function getClientIp()
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            return trim($ips[0]);
        } else {
            return $_SERVER["REMOTE_ADDR"];
        }
    }

    /**
    * Gets the data from a URL using CURL
    * @param String
    * @return String
    */
    private function getData($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
