<?php
class soapApi {
    protected $resellerID;
    protected $apiKey;
    protected $soap;

    public function getApiKey() {
        return $this->apiKey;
    }
    public function getResellerID() {
        return $this->resellerID;
    }
    public function __construct($resellerID, $apiKey) {
        $this->soap = new SoapClient(NULL, 
            [
                'location' => 'https://api.synergywholesale.com', 
                'uri' => '', 
                'trace' => true,
                'exceptions' => true
            ]
        );
        $this->apiKey = $apiKey;
        $this->resellerID = $resellerID;
    }
    
    public function request($command, $params = array()) {
        $params['resellerID'] = $this->getResellerID();
        $params['apiKey'] = $this->getApiKey();
        $response = $this->soap->$command($params);
        return $response;
    }
    
    public function terminateAccount($params) {
        $command = "hostingTerminateService";
        $response = $this->request($command, $params);
        return $response;
    }

    public function createAccount($params) {
        $command = "hostingPurchaseService";
        $response = $this->request($command, $params);
        return $response;
    }
    
    public function balanceQuery($params) {
        $command = "balanceQuery";
        $response = $this->request($command, $params);
        return $response;
    }


    public function changePass($params) {
        $command = "hostingChangePassword";
        $response = $this->request($command, $params);
        return $response;
    }


    public function suspendAccount($params) {
        $command = "hostingSuspendService";
        $response = $this->request($command, $params);
        return $response;
    }
    public function recreateAccount($params) {
        $command = "hostingRecreateService";
        $response = $this->request($command, $params);
        return $response;
    }

    public function unsuspendAccount($params) {
        $command = "hostingUnsuspendService";
        $response = $this->request($command, $params);
        return $response;
    }
    
    public function changePackage($params) {
        $command = "hostingChangePackage";
        $response = $this->request($command, $params);
        return $response;
    }
    
    public function syncData($params) {
        $command = "hostingGetService";
        $response = $this->request($command, $params);
        return $response;
    }

    public function hostingPurchaseService($params) {
        $command = "hostingPurchaseService";
        $response = $this->request($command, $params);
        return $response;
    }
    
    public function hostingGetService($params) {
        $command = "hostingGetService";
        $response = $this->request($command, $params);
        return $response;
    }
    
}
