<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Pbxcall_lib { 
    private $CI;
	private $username;
	private $password;
    private $port;
    private $context;
    private $host;
	public function __construct() { 
        $this ->CI = & get_instance();
		$this->CI->load->config("pbxcall",true);
		$this->username = $this->CI->config->item('username', 'pbxcall');
		$this->password = $this->CI->config->item('password', 'pbxcall');
        $this->port = $this->CI->config->item('port', 'pbxcall');
		$this->context = $this->CI->config->item('context', 'pbxcall');
        $this->host = $this->CI->config->item('host', 'pbxcall');
    }

    public function call($internalPhone, $targetPhone) {
        // Internal phone line to call from
        $internalPhoneline = "local/".$internalPhone."@from-internal";
        $target = $targetPhone;
        $context = $this->context;
        try{

            $socket = stream_socket_client("tcp://$this->host:$this->port");
            if($socket)
            {
                //echo "Connected to socket, sending authentication request.\n";

                // Prepare authentication request
                $authenticationRequest = "Action: Login\r\n";
                $authenticationRequest .= "Username: $this->username\r\n";
                $authenticationRequest .= "Secret: $this->password\r\n";
                $authenticationRequest .= "Events: off\r\n\r\n";

                // Send authentication request
                $authenticate = stream_socket_sendto($socket, $authenticationRequest);
                if($authenticate > 0)
                {
                    // Wait for server response
                    usleep(200000);

                    // Read server response
                    $authenticateResponse = fread($socket, 4096);

                    // Check if authentication was successful
                    if(strpos($authenticateResponse, 'Success') !== false)
                    {
                       // echo "Authenticated to Asterisk Manager Inteface. Initiating call.\n";

                        // Prepare originate request
                        $originateRequest = "Action: Originate\r\n";
                        $originateRequest .= "Channel: $internalPhoneline\r\n";
                        $originateRequest .= "Callerid: Click 2 Call\r\n";
                        $originateRequest .= "Exten: $target\r\n";
                        $originateRequest .= "Context: $context\r\n";
                        $originateRequest .= "Priority: 1\r\n";
                        $originateRequest .= "Async: yes\r\n\r\n";

                        // Send originate request
                        $originate = stream_socket_sendto($socket, $originateRequest);
                        if($originate > 0)
                        {
                            // Wait for server response
                            usleep(200000);

                            // Read server response
                            $originateResponse = fread($socket, 4096);
                            //echo $originateResponse;
                            // Check if originate was successful
                            if(strpos($originateResponse, 'Success') !== false)
                            {
                                $response["status"] = 200;
                                $response["message"] = "Call initiated, dialing.";
                                return $response;
                            } else {
                                //throw new Exception("Could not initiate call.",500);
                                $response["status"] = 200;
                                $response["message"] = "Could not initiate call.";
                                return $response;
                            }
                        } else {
                            throw new Exception("Could not write call initiation request to socket.", 500);
                        }
                    } else {
                        throw new Exception("Could not authenticate to Asterisk Manager Interface.", 500);
                    }
                } else {
                    throw new Exception("Could not write authentication request to socket.", 500);
                }
            } else {
                throw new Exception("Unable to connect to socket.",500);
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}
